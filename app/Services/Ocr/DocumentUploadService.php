<?php

namespace App\Services\Ocr;

use App\Jobs\ProcessOcrDocument;
use App\Models\OcrDocument;
use App\Repositories\OcrAuditLogRepository;
use App\Repositories\OcrDocumentRepository;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DocumentUploadService
{
    public function __construct(
        private OcrDocumentRepository $documents,
        private OcrAuditLogRepository $audit,
        private DocumentStorageService $storage,
    ) {}

    /**
     * Upload file, dedupe theo sha256, persist + dispatch job.
     * Trả về tuple [OcrDocument, bool $isDuplicate].
     */
    public function handle(UploadedFile $file, ?string $apiKeyLabel = null): array
    {
        $hash = $this->storage->hashFile($file);

        // AC-E05: idempotency 24h. Trả về cached khi cùng hash + còn trong window.
        $cached = $this->documents->findCachedByHash($hash);
        if ($cached) {
            $this->audit->log([
                'document_id' => $cached->id,
                'stage' => 0,
                'event_name' => 'upload_cache_hit',
                'payload' => ['hash' => $hash, 'cached_until' => $cached->cached_until?->toIso8601String()],
            ]);
            $cached->load('extraction');
            return [$cached, true];
        }

        // Fallback: cùng hash đã tồn tại nhưng cache hết hạn (>24h) hoặc status failed/processing.
        // Hash UNIQUE constraint trong DB → không thể tạo doc mới cùng hash. Luôn return existing.
        $existing = $this->documents->findByHash($hash);
        if ($existing) {
            $this->audit->log([
                'document_id' => $existing->id,
                'stage' => 0,
                'event_name' => 'upload_dedupe_hit_non_cached',
                'payload' => ['hash' => $hash, 'status' => $existing->status],
            ]);

            // Nếu doc cũ status=done nhưng cached_until expired → extend cache window
            // để lần upload tiếp theo trong 24h tới hit cached path nhanh.
            if ($existing->status === OcrDocument::STATUS_DONE) {
                $this->documents->markProcessedAt(
                    $existing->id,
                    (int) config('ocr.idempotency_window_hours', 24),
                );
            }

            $existing->load('extraction');
            return [$existing, true];
        }

        $relativePath = $this->storage->store($file, $hash);

        return DB::transaction(function () use ($file, $hash, $relativePath, $apiKeyLabel) {
            $doc = $this->documents->create([
                'hash' => $hash,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'storage_path' => $relativePath,
                'status' => OcrDocument::STATUS_PENDING,
                'uploaded_via_api_key_label' => $apiKeyLabel,
            ]);

            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 0,
                'event_name' => 'upload_persisted',
                'payload' => [
                    'mime' => $doc->mime,
                    'size_bytes' => $doc->size_bytes,
                ],
            ]);

            ProcessOcrDocument::dispatch($doc->id)
                ->onQueue(config('ocr.queue', 'default'));

            // In sync queue mode the job has already run by this point — refresh + load
            // extraction so the response can include the final result.
            $doc->refresh()->load('extraction');

            return [$doc, false];
        });
    }
}
