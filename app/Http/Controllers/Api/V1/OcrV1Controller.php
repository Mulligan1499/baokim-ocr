<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ExtractRequest;
use App\Http\Resources\V1\DocumentV1Resource;
use App\Models\OcrDocument;
use App\Repositories\OcrDocumentRepository;
use App\Services\Ocr\DocumentUploadService;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 *
 * Endpoints:
 *   POST /api/v1/ocr/extract               AC-01, 02, 03, 04
 *   GET  /api/v1/ocr/history/{request_id}  AC-05
 *   GET  /api/v1/ocr/history               AC-06
 */
class OcrV1Controller extends Controller
{
    public function __construct(
        private DocumentUploadService $uploadService,
        private OcrDocumentRepository $documents,
    ) {
    }

    public function extract(ExtractRequest $request): JsonResponse
    {
        @set_time_limit(0);

        $apiKeyLabel = $request->attributes->get('api_key_label');

        try {
            [$doc, $isDuplicate] = $this->uploadService->handle(
                $request->file('file'),
                $apiKeyLabel,
            );
        } catch (Throwable $e) {
            return ApiErrorResponse::make(
                'OCR_SERVICE_UNAVAILABLE',
                'Hệ thống đang quá tải, vui lòng thử lại sau.',
                'OCR service temporarily unavailable.',
                503,
                retryAfter: 60,
            );
        }

        $doc->load(['extraction.text']);

        if ($doc->status === OcrDocument::STATUS_FAILED) {
            // AC-AI-04 + AC-E04: KHÔNG crash. Trả 200 với warning + value rỗng.
            return response()->json(
                $this->buildFailedResponse($doc, $isDuplicate),
                200,
            );
        }

        // Sync mode: extraction đã có; async mode: trả 202 với placeholder.
        if ($doc->status !== OcrDocument::STATUS_DONE) {
            return response()->json([
                'request_id' => $doc->request_id,
                'status' => $doc->status,
                'message_vi' => 'Đang xử lý. Vui lòng poll GET /api/v1/ocr/history/{request_id}.',
                'message_en' => 'Processing. Poll GET /api/v1/ocr/history/{request_id}.',
            ], 202);
        }

        $payload = (new DocumentV1Resource($doc))->withCachedFlag($isDuplicate)->toArray($request);
        return response()->json($payload, 200);
    }

    /**
     * AC-05: lookup theo `request_id` (UUID v4) — AC contract chính.
     * Bonus: support `numeric id` cho debug nhanh trên DB.
     */
    public function showByKey(string $key, Request $request): JsonResponse
    {
        $doc = ctype_digit($key)
            ? $this->documents->findById((int) $key)
            : $this->documents->findByRequestId($key);

        if (!$doc) {
            return ApiErrorResponse::make(
                'NOT_FOUND',
                'Không tìm thấy tài liệu này.',
                'Document not found.',
                404,
                requestId: $key,
            );
        }

        $doc->load(['extraction.text']);
        $payload = (new DocumentV1Resource($doc))->withCachedFlag(false)->toArray($request);
        return response()->json($payload, 200);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $filters = array_filter([
            'status' => $request->query('status'),
        ]);

        $result = $this->documents->listForHistory($limit, $offset, $filters);

        $items = $result['items']->map(function (OcrDocument $d) {
            $ext = $d->extraction;
            $lang = $ext?->language_detected ?? 'other';
            if (!in_array($lang, ['vi', 'en', 'zh', 'mixed'], true)) {
                $lang = 'other';
            }
            return [
                'request_id' => $d->request_id,
                'uploaded_at' => $d->created_at?->toIso8601String(),
                'document_type' => $ext?->doc_type ?? 'unknown',
                'language_detected' => $lang,
                'overall_confidence' => (float) ($ext?->confidence_overall ?? 0),
                'file_name' => $d->original_name,
                'status' => $d->status,
            ];
        })->all();

        return response()->json([
            'items' => $items,
            'limit' => $limit,
            'offset' => $offset,
            'total' => $result['total'],
        ], 200);
    }

    private function buildFailedResponse(OcrDocument $doc, bool $cached): array
    {
        return [
            'request_id' => $doc->request_id,
            'status' => 'failed',
            'document_id' => $doc->id,
            'file_name' => $doc->original_name,
            'file_hash' => $doc->hash,
            'file_size_bytes' => $doc->size_bytes,
            'mime' => $doc->mime,
            'uploaded_at' => $doc->created_at?->toIso8601String(),
            'uploaded_by' => $doc->uploaded_via_api_key_label,
            'cached' => $cached,
            'result' => [
                'request_id' => $doc->request_id,
                'raw_text' => '',
                'key_value_pairs' => [],
                'language_detected' => 'other',
                'translation_vi' => null,
                'overall_confidence' => 0.0,
                'document_type' => 'unknown',
                'page_count' => 0,
                'processed_at' => $doc->processed_at?->toIso8601String(),
                'ai_model_version' => null,
                'warnings' => ['NO_TEXT_DETECTED'],
                'quality' => 'low',
                'requires_review' => true,
            ],
            'error_message_vi' => 'Không trích xuất được nội dung từ file này.',
        ];
    }
}
