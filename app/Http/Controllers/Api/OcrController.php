<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Repositories\OcrDocumentRepository;
use App\Services\Ocr\DocumentUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 3 endpoints API OCR. Controller mỏng theo BKM03 — chỉ:
 *   1. Đọc Request đã validate (FormRequest = Stage 0)
 *   2. Gọi Service business
 *   3. Format Response qua Resource
 *
 * Swagger / OpenAPI annotations cho 3 endpoint nằm hoàn toàn ở
 * `app/Swagger/SwaggerInfo.php` để controller sạch dễ đọc.
 */
class OcrController extends Controller
{
    public function __construct(
        private DocumentUploadService $uploadService,
        private OcrDocumentRepository $documents,
    ) {
    }

    /**
     * POST /api/ocr/process — upload + xử lý OCR.
     *
     * Sync mode (QUEUE_CONNECTION=sync): trả full extraction trong response 200.
     * Async mode: trả document_id + status=pending, 202. Poll GET sau.
     */
    public function process(ProcessDocumentRequest $request): JsonResponse
    {
        // Sync mode: 3 LLM calls có thể ~30-45s. Override PHP timeout cho request này
        // (artisan serve sub-process không nhận -d max_execution_time từ CLI).
        @set_time_limit(0);

        $apiKeyLabel = $request->attributes->get('api_key_label');
        Log::info(111);
        Log::info($apiKeyLabel);

        [$doc, $isDuplicate] = $this->uploadService->handle(
            $request->file('file'),
            $apiKeyLabel,
        );

        if (in_array($doc->status, ['done', 'failed'], true)) {
            $doc->load('extraction');
            $payload = (new DocumentResource($doc))->toArray($request);
            $payload['duplicate'] = $isDuplicate;
            return response()->json($payload, 200);
        }

        return response()->json([
            'document_id' => $doc->id,
            'status' => $doc->status,
            'duplicate' => $isDuplicate,
            'message' => $isDuplicate
                ? 'File đã tồn tại (cùng sha256). Trả về document_id cũ.'
                : 'Document đã queue để xử lý. Poll GET /api/ocr/{id} để lấy kết quả.',
        ], $isDuplicate ? 200 : 202);
    }

    /**
     * GET /api/ocr/{id} — lấy kết quả 1 document.
     *
     * Query param `?view=raw` (default, KSNB copy-paste) hoặc `?view=masked` (PII đã che).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $doc = $this->documents->findById($id);
        if (!$doc) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $doc->load('extraction');

        return response()->json(
            (new DocumentResource($doc))->toArray($request)
        );
    }

    /**
     * GET /api/ocr — list paginated, filter qua ?status= ?per_page=.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);
        $perPage = (int) $request->query('per_page', 20);
        $page = $this->documents->paginateWithFilters($filters, min($perPage, 100));

        return response()->json([
            'data' => collect($page->items())->map(fn($d) => [
                'document_id' => $d->id,
                'status' => $d->status,
                'original_name' => $d->original_name,
                'mime' => $d->mime,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
