<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

/**
 * OpenAPI documentation cho Baokim OCR API.
 *
 * Annotations tách riêng khỏi controllers để giữ controller mỏng (BKM03).
 * swagger-php v6 yêu cầu PHP 8 attributes (không hỗ trợ phpdoc `@OA\…`),
 * vì vậy file này dùng cú pháp `#[OA\...]`.
 *
 * Endpoints:
 *   POST /api/v1/ocr/extract              Upload + extract document
 *   GET  /api/v1/ocr/history/{key}        Retrieve một kết quả
 *   GET  /api/v1/ocr/history              List paginated lịch sử
 *
 * Re-generate: `php artisan l5-swagger:generate`
 */
#[OA\Info(
    version: '1.0.0',
    description: <<<DESC
REST API bóc tách thông tin có cấu trúc từ tài liệu định danh, hợp đồng, hóa đơn,
văn bản pháp lý, báo cáo tài chính, v.v. Hỗ trợ tiếng Việt + tiếng Anh + tiếng Trung,
tự động dịch nội dung ngoại ngữ sang tiếng Việt.

## Capabilities

- **Input formats**: JPEG, PNG, WEBP, PDF (single và multi-page, cap 20 trang)
- **File size**: ≤ 10MB
- **Languages**: `vi`, `en`, `zh` (+ `mixed`, `other` fallback)
- **Document types** (16 loại): CCCD/CMND, CMND nước ngoài, Hộ chiếu, Giấy phép kinh doanh,
  Hợp đồng (VI/EN/ZH), Hóa đơn, Văn bản pháp lý, Tờ khai hải quan, Vận đơn, Điều lệ AML,
  Giấy ủy quyền, Hợp đồng lao động, Báo cáo tài chính, fallback `other`
- **Output**: raw text + key-value pairs + Vietnamese translation + per-field confidence + warnings
- **History**: mỗi lần gọi có `request_id` UUID v4 để retrieve sau

## Authentication

Mọi endpoint yêu cầu header `X-API-Key`. Key cấp theo consumer, rotate qua `.env`.

## Idempotency

Cùng file (sha256 hash) upload trong vòng 24h → trả về kết quả cached (cờ `cached=true`).
Bypass cache: thêm query param `?force=true` để chạy lại pipeline.

## Error contract

Mọi error response tuân theo schema thống nhất `{error_code, message_vi, message_en, request_id, retry_after?}`.
HTTP 503 + 429 kèm header `Retry-After` (giây).

## Latency

Pipeline chạy đồng bộ: p95 ~15-45s cho ảnh đơn, lâu hơn cho PDF nhiều trang.
Client nên đặt timeout ≥ 60s.
DESC,
    title: 'Baokim OCR API',
    contact: new OA\Contact(name: 'Baokim Engineering', email: 'dev@baokim.vn'),
)]
#[OA\Server(url: '/', description: 'Local server')]
#[OA\SecurityScheme(securityScheme: 'api_key', type: 'apiKey', name: 'X-API-Key', in: 'header')]

#[OA\Tag(name: 'OCR v1', description: 'Document extraction endpoints')]

// ─────────────────────────────────────────────────────────────────────
// POST /api/v1/ocr/extract
// ─────────────────────────────────────────────────────────────────────
#[OA\Post(
    path: '/api/v1/ocr/extract',
    summary: 'Upload tài liệu, OCR + extract + dịch',
    description: <<<DESC
Bóc tách raw text + key-value pairs từ ảnh hoặc PDF. Nếu ngôn ngữ ≠ `vi`, kết quả
kèm bản dịch tiếng Việt.

Response 200 trả full result. Trả 202 nếu xử lý async (queue chưa hoàn tất).

**Idempotency**: cùng file (sha256) trong 24h → trả cached. Thêm `?force=true` để bypass cache.

**Multi-page PDF**: `raw_text` và `translation_vi` có marker `=== Trang N ===` giữa các trang.
DESC,
    security: [['api_key' => []]],
    tags: ['OCR v1'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: 'Image (JPEG/PNG/WEBP) hoặc PDF, ≤ 10MB. Chỉ 1 file mỗi request.',
                        type: 'string',
                        format: 'binary',
                    ),
                    new OA\Property(
                        property: 'force',
                        description: 'Set `true` để bypass cache 24h và chạy lại pipeline.',
                        type: 'boolean',
                        default: false,
                    ),
                ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'OK — extraction hoàn tất',
            content: new OA\JsonContent(ref: '#/components/schemas/V1Document'),
        ),
        new OA\Response(response: 202, description: 'Đang xử lý — poll GET history với request_id'),
        new OA\Response(
            response: 400,
            description: 'EMPTY_FILE / INVALID_FILE_FORMAT / MULTIPLE_FILES_NOT_ALLOWED',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/V1Error'),
                examples: [
                    'EMPTY_FILE' => new OA\Examples(
                        example: 'EMPTY_FILE',
                        summary: 'File rỗng (0 byte)',
                        value: [
                            'error_code' => 'EMPTY_FILE',
                            'message_vi' => 'File rỗng hoặc không có nội dung.',
                            'message_en' => 'File is empty or has no content.',
                            'request_id' => '4d9c8b1a-2e7f-4c3a-b6d8-1e2f3a4b5c6d',
                        ],
                    ),
                    'INVALID_FILE_FORMAT' => new OA\Examples(
                        example: 'INVALID_FILE_FORMAT',
                        summary: 'File không phải JPEG/PNG/WEBP/PDF',
                        value: [
                            'error_code' => 'INVALID_FILE_FORMAT',
                            'message_vi' => 'Định dạng không hợp lệ. Chỉ nhận JPEG, PNG, WEBP, PDF.',
                            'message_en' => 'Invalid file format. Only JPEG, PNG, WEBP, PDF are accepted.',
                            'request_id' => '23221d4a-8220-4d23-9e8a-36d74c638412',
                        ],
                    ),
                    'MULTIPLE_FILES_NOT_ALLOWED' => new OA\Examples(
                        example: 'MULTIPLE_FILES_NOT_ALLOWED',
                        summary: 'Upload nhiều file/lần',
                        value: [
                            'error_code' => 'MULTIPLE_FILES_NOT_ALLOWED',
                            'message_vi' => 'Chỉ chấp nhận 1 file mỗi lần upload. Vui lòng tải lại với 1 file duy nhất.',
                            'message_en' => 'Only one file per upload allowed.',
                            'request_id' => '8b4e1f29-3c5a-4d76-9e8f-2a1b3c4d5e6f',
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED — thiếu hoặc sai X-API-Key',
            content: new OA\JsonContent(
                ref: '#/components/schemas/V1Error',
                example: [
                    'error_code' => 'UNAUTHORIZED',
                    'message_vi' => 'Khóa API không hợp lệ hoặc thiếu header X-API-Key.',
                    'message_en' => 'Invalid or missing API key. Provide header X-API-Key.',
                    'request_id' => '66be0400-57ed-4bc9-81a5-0f36f49212b3',
                ],
            ),
        ),
        new OA\Response(
            response: 413,
            description: 'FILE_TOO_LARGE — > 10MB',
            content: new OA\JsonContent(
                ref: '#/components/schemas/V1Error',
                example: [
                    'error_code' => 'FILE_TOO_LARGE',
                    'message_vi' => 'File vượt quá 10MB.',
                    'message_en' => 'File exceeds 10MB limit.',
                    'request_id' => '62f7006a-6952-4b95-bd79-0a52d353aea1',
                ],
            ),
        ),
        new OA\Response(
            response: 422,
            description: 'MISSING_FILE / VALIDATION_FAILED',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/V1Error'),
                examples: [
                    'MISSING_FILE' => new OA\Examples(
                        example: 'MISSING_FILE',
                        summary: 'Thiếu field `file` trong request',
                        value: [
                            'error_code' => 'MISSING_FILE',
                            'message_vi' => 'Vui lòng đính kèm file (multipart field "file").',
                            'message_en' => 'File field is required.',
                            'request_id' => '15b0939b-af6c-4bf5-9e7a-b4a0b58a49bc',
                        ],
                    ),
                    'VALIDATION_FAILED' => new OA\Examples(
                        example: 'VALIDATION_FAILED',
                        summary: 'Validation fail khác',
                        value: [
                            'error_code' => 'VALIDATION_FAILED',
                            'message_vi' => 'Dữ liệu không hợp lệ.',
                            'message_en' => 'Validation failed.',
                            'request_id' => 'aa04748d-d4fc-4f93-a2a1-8222d0c0e7a4',
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 503,
            description: 'OCR_SERVICE_UNAVAILABLE — vendor downstream lỗi, retry theo header Retry-After',
            content: new OA\JsonContent(
                ref: '#/components/schemas/V1Error',
                example: [
                    'error_code' => 'OCR_SERVICE_UNAVAILABLE',
                    'message_vi' => 'Hệ thống đang quá tải, vui lòng thử lại sau.',
                    'message_en' => 'OCR service temporarily unavailable.',
                    'request_id' => 'aa04748d-d4fc-4f93-a2a1-8222d0c0e7a4',
                    'retry_after' => 60,
                ],
            ),
        ),
    ],
)]

// ─────────────────────────────────────────────────────────────────────
// GET /api/v1/ocr/history/{key}
// ─────────────────────────────────────────────────────────────────────
#[OA\Get(
    path: '/api/v1/ocr/history/{key}',
    summary: 'Truy xuất một kết quả OCR',
    description: 'Lookup theo `request_id` (UUID v4) hoặc `document_id` numeric (debug alias).',
    security: [['api_key' => []]],
    tags: ['OCR v1'],
    parameters: [
        new OA\Parameter(
            name: 'key',
            in: 'path',
            required: true,
            description: 'UUID v4 hoặc numeric document_id',
            schema: new OA\Schema(type: 'string'),
            examples: [
                'uuid' => new OA\Examples(example: 'uuid', summary: 'request_id UUID', value: '87ef1549-64ca-4064-860d-23d23278798f'),
                'numeric' => new OA\Examples(example: 'numeric', summary: 'numeric id', value: '17'),
            ],
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'OK',
            content: new OA\JsonContent(ref: '#/components/schemas/V1Document'),
        ),
        new OA\Response(
            response: 404,
            description: 'NOT_FOUND',
            content: new OA\JsonContent(
                ref: '#/components/schemas/V1Error',
                example: [
                    'error_code' => 'NOT_FOUND',
                    'message_vi' => 'Không tìm thấy tài liệu này.',
                    'message_en' => 'Document not found.',
                    'request_id' => '99999',
                ],
            ),
        ),
    ],
)]

// ─────────────────────────────────────────────────────────────────────
// GET /api/v1/ocr/history
// ─────────────────────────────────────────────────────────────────────
#[OA\Get(
    path: '/api/v1/ocr/history',
    summary: 'List lịch sử OCR (mới nhất trước)',
    description: 'Trả về danh sách documents đã xử lý, sắp xếp theo thời gian giảm dần. Hỗ trợ filter theo status.',
    security: [['api_key' => []]],
    tags: ['OCR v1'],
    parameters: [
        new OA\Parameter(name: 'limit', in: 'query', description: 'Số record mỗi trang (max 100)', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        new OA\Parameter(name: 'offset', in: 'query', description: 'Skip N records', schema: new OA\Schema(type: 'integer', default: 0)),
        new OA\Parameter(name: 'status', in: 'query', description: 'Filter theo trạng thái xử lý', schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'done', 'failed'])),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'offset', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                ],
            ),
        ),
    ],
)]

// ─────────────────────────────────────────────────────────────────────
// Components / Schemas
// ─────────────────────────────────────────────────────────────────────
#[OA\Schema(
    schema: 'V1KeyValuePair',
    type: 'object',
    description: 'Một trường được extract từ tài liệu',
    properties: [
        new OA\Property(property: 'key', type: 'string', description: 'Tên trường (snake_case)', example: 'so_cccd'),
        new OA\Property(property: 'value', type: 'string', description: 'Giá trị nguyên bản. Rỗng nếu không đọc được — không bịa', example: '001234567890'),
        new OA\Property(property: 'confidence', type: 'number', format: 'float', description: 'Độ tin cậy trong [0, 1]', example: 0.96),
        new OA\Property(property: 'flagged', type: 'boolean', description: 'True nếu confidence thấp hoặc sai format — cần review', example: false),
        new OA\Property(property: 'value_translated_vi', type: 'string', nullable: true, description: 'Bản dịch tiếng Việt (nếu trường có text non-VN)', example: null),
        new OA\Property(property: 'name_phonetic_vi', type: 'string', nullable: true, description: 'Phiên âm Hán-Việt (cho tên người Trung)', example: null),
    ],
)]
#[OA\Schema(
    schema: 'V1ExtractionResult',
    type: 'object',
    description: 'Kết quả OCR sau khi pipeline xử lý xong',
    properties: [
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'raw_text', type: 'string', description: 'Toàn bộ text trong tài liệu. Multi-page PDF có marker `=== Trang N ===`'),
        new OA\Property(property: 'key_value_pairs', type: 'array', items: new OA\Items(ref: '#/components/schemas/V1KeyValuePair')),
        new OA\Property(property: 'language_detected', type: 'string', enum: ['vi', 'en', 'zh', 'mixed', 'other']),
        new OA\Property(property: 'translation_vi', type: 'string', nullable: true, description: 'Bản dịch tiếng Việt toàn văn (null nếu language=vi)'),
        new OA\Property(property: 'overall_confidence', type: 'number', format: 'float', description: 'Weighted average — critical fields có weight 2', example: 0.82),
        new OA\Property(property: 'document_type', type: 'string', description: 'Loại tài liệu (cccd, passport, gpkd, invoice, ...) hoặc `unknown`', example: 'cccd'),
        new OA\Property(property: 'page_count', type: 'integer', example: 1),
        new OA\Property(property: 'processed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ai_model_version', type: 'string', description: 'Phiên bản AI model dùng cho extraction', example: 'gemini-2.5-flash'),
        new OA\Property(property: 'warnings', type: 'array', items: new OA\Items(type: 'string'), description: 'Danh sách warning codes (MEDIUM_CONFIDENCE, MIXED_LANGUAGE, missing_field:X, ...)'),
        new OA\Property(property: 'quality', type: 'string', enum: ['high', 'medium', 'low']),
        new OA\Property(property: 'requires_review', type: 'boolean', description: 'True nếu cần KSNB kiểm tra lại thủ công'),
    ],
)]
#[OA\Schema(
    schema: 'V1Document',
    type: 'object',
    description: 'Document envelope chứa metadata + extraction result',
    properties: [
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'done', 'failed']),
        new OA\Property(property: 'document_id', type: 'integer'),
        new OA\Property(property: 'file_name', type: 'string'),
        new OA\Property(property: 'file_hash', type: 'string', description: 'sha256 của file content (dùng cho idempotency)'),
        new OA\Property(property: 'file_size_bytes', type: 'integer'),
        new OA\Property(property: 'mime', type: 'string'),
        new OA\Property(property: 'uploaded_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'uploaded_by', type: 'string', nullable: true, description: 'API key label của consumer'),
        new OA\Property(property: 'cached', type: 'boolean', description: 'True nếu là cache hit (cùng file trong 24h gần nhất)'),
        new OA\Property(property: 'result', ref: '#/components/schemas/V1ExtractionResult', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'V1Error',
    type: 'object',
    description: 'Standard error response — đồng nhất cho mọi 4xx/5xx. Xem example cụ thể trong từng response.',
    required: ['error_code', 'message_vi', 'message_en', 'request_id'],
    properties: [
        new OA\Property(property: 'error_code', type: 'string', description: 'SCREAMING_SNAKE_CASE — stable contract per status code'),
        new OA\Property(property: 'message_vi', type: 'string', description: 'Thông báo tiếng Việt cho end-user'),
        new OA\Property(property: 'message_en', type: 'string', description: 'Thông báo tiếng Anh cho dev/log'),
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid', description: 'UUID v4 trace, lưu trong audit log'),
        new OA\Property(property: 'retry_after', type: 'integer', nullable: true, description: 'Số giây client nên đợi trước khi retry — chỉ có ở 503/429'),
    ],
)]

class SwaggerInfo
{
}
