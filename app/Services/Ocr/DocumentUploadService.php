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

        $existing = $this->documents->findByHash($hash);
        if ($existing) {
            $this->audit->log([
                'document_id' => $existing->id,
                'stage' => 0,
                'event' => 'upload_dedupe_hit',
                'payload' => ['hash' => $hash],
            ]);
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
                'event' => 'upload_persisted',
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
