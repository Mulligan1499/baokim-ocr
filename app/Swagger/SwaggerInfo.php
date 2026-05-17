<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

/**
 * Swagger / OpenAPI documentation for the OCR API.
 *
 * Toàn bộ Swagger annotations tách riêng khỏi controller để controller sạch
 * thuần Laravel code. swagger-php v6 (l5-swagger 11) yêu cầu PHP 8 attributes,
 * không hỗ trợ phpdoc @OA\... — vì vậy file này hơi đặc biệt một chút.
 *
 * Sửa annotations ở đây không cần đụng vào OcrController.
 */
#[OA\Info(
    version: '0.1.0',
    description: 'API OCR cho KSNB onboarding merchant. Nhận ảnh/PDF (VI/EN/ZH), trả về text đầy đủ + key-values + translation tiếng Việt + confidence per field. RAW response cho KSNB copy-paste; ?view=masked cho audit/export.',
    title: 'Baokim OCR API',
)]
#[OA\Server(url: '/', description: 'Local server')]
#[OA\SecurityScheme(securityScheme: 'api_key', type: 'apiKey', name: 'X-API-Key', in: 'header')]

#[OA\Post(
    path: '/api/ocr/process',
    summary: 'Upload tài liệu để OCR',
    security: [['api_key' => []]],
    tags: ['OCR'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(
                        property: 'file',
                        description: 'Image (JPEG/PNG/WEBP) hoặc PDF, max 25MB. Word/Excel chưa hỗ trợ — Save As → PDF trước.',
                        type: 'string',
                        format: 'binary',
                    ),
                ],
            ),
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Sync: pipeline finished, full extraction in body. Hoặc duplicate hit (cùng sha256).'),
        new OA\Response(response: 202, description: 'Async: job queued. Poll GET /api/ocr/{id} để lấy kết quả.'),
        new OA\Response(response: 401, description: 'Missing/invalid API key'),
        new OA\Response(response: 422, description: 'Validation failed (file size/MIME)'),
    ],
)]

#[OA\Get(
    path: '/api/ocr/{id}',
    summary: 'Lấy kết quả OCR của 1 document',
    security: [['api_key' => []]],
    tags: ['OCR'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(
            name: 'view',
            in: 'query',
            required: false,
            description: 'raw (default — KSNB copy-paste) hoặc masked (PII đã che)',
            schema: new OA\Schema(type: 'string', enum: ['raw', 'masked'], default: 'raw'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'OK — document + extraction result'),
        new OA\Response(response: 404, description: 'Document không tồn tại'),
    ],
)]

#[OA\Get(
    path: '/api/ocr',
    summary: 'List documents có filter',
    security: [['api_key' => []]],
    tags: ['OCR'],
    parameters: [
        new OA\Parameter(
            name: 'status',
            in: 'query',
            schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'done', 'failed']),
        ),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated list'),
    ],
)]
class SwaggerInfo
{
}
