# Templates — Baokim API Design

Reference file lazy-load. Copy/adapt cho endpoint cụ thể.
Cho 8 quyết định kiến trúc (CRITICAL) → xem [../SKILL.md](../SKILL.md).

## 1. Route file structure

`routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\V1\OcrV1Controller;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.apikey')->prefix('v1')->group(function () {
    Route::post('/ocr/extract', [OcrV1Controller::class, 'extract'])
        ->name('v1.ocr.extract');

    Route::get('/ocr/history', [OcrV1Controller::class, 'history'])
        ->name('v1.ocr.history.list');

    Route::get('/ocr/history/{key}', [OcrV1Controller::class, 'showByKey'])
        ->where('key', '[0-9a-fA-F\-]{36}|[0-9]+')
        ->name('v1.ocr.history.show');
});
```

**Notes**:
- Tất cả routes trong group `prefix('v1')` — không tạo từng `/api/v1/...` lặp
- Middleware `auth.apikey` apply group-level
- Regex constraint `where(...)` cho path param — chấp nhận cả UUID lẫn numeric id

## 2. ApiErrorResponse helper

`app/Support/ApiErrorResponse.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiErrorResponse
{
    public static function make(
        string $code,
        string $messageVi,
        string $messageEn,
        int $status = 400,
        ?int $retryAfter = null,
        ?string $requestId = null,
    ): JsonResponse {
        $body = [
            'error_code' => $code,
            'message_vi' => $messageVi,
            'message_en' => $messageEn,
            'request_id' => $requestId ?: (string) Str::uuid(),
        ];
        if ($retryAfter !== null) {
            $body['retry_after'] = $retryAfter;
        }

        $resp = response()->json($body, $status);
        if ($retryAfter !== null) {
            $resp->header('Retry-After', (string) $retryAfter);
        }
        return $resp;
    }
}
```

## 3. FormRequest pattern với error mapping

`app/Http/Requests/V1/CreateXyzRequest.php`:

```php
<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class CreateXyzRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        [$code, $vi, $en, $status] = $this->translateFailure($errors);

        throw new HttpResponseException(
            response()->json([
                'error_code' => $code,
                'message_vi' => $vi,
                'message_en' => $en,
                'request_id' => (string) Str::uuid(),
            ], $status)
        );
    }

    private function translateFailure($errors): array
    {
        $msg = $errors->first();
        if (str_contains($msg, 'required')) {
            return ['MISSING_FIELD', 'Thiếu trường bắt buộc.', 'Required field missing.', 422];
        }
        return ['VALIDATION_FAILED', 'Dữ liệu không hợp lệ.', 'Validation failed.', 422];
    }
}
```

## 4. Resource class V1

`app/Http/Resources/V1/XyzV1Resource.php`:

```php
<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class XyzV1Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->request_id,
            'status' => $this->status,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'created_at' => $this->created_at?->toIso8601String(),
            // KHÔNG expose internal id nếu có UUID
            // KHÔNG nest trong 'data' envelope
        ];
    }
}
```

## 5. Idempotency repository methods

```php
// Hash-based (file upload)
public function findCachedByHash(string $hash): ?Model
{
    return Model::where('hash', $hash)
        ->where('status', 'done')
        ->where('cached_until', '>', now())
        ->first();
}

public function markProcessedAt(int $id, int $cacheHours): void
{
    Model::where('id', $id)->update([
        'processed_at' => now(),
        'cached_until' => now()->addHours($cacheHours),
    ]);
}

// Idempotency-Key header (create operations)
public function findByIdempotencyKey(string $key): ?Model
{
    return Model::where('idempotency_key', $key)
        ->where('created_at', '>', now()->subHours(24))
        ->first();
}
```

## 6. Middleware auth.apikey

`app/Http/Middleware/AuthenticateApiKey.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('xyz.api_key');
        $header = config('xyz.api_key_header', 'X-API-Key');
        $provided = $request->header($header);

        if (! $expected) {
            return ApiErrorResponse::make(
                'SERVER_MISCONFIGURED',
                'Hệ thống chưa cấu hình khóa API.',
                'API key not configured on server.',
                500,
            );
        }

        if (! $provided || ! hash_equals($expected, $provided)) {
            $resp = ApiErrorResponse::make(
                'UNAUTHORIZED',
                'Khóa API không hợp lệ hoặc thiếu header ' . $header . '.',
                'Invalid or missing API key. Provide header ' . $header . '.',
                401,
            );
            $resp->header('WWW-Authenticate', 'ApiKey realm="xyz-api"');
            return $resp;
        }

        $request->attributes->set('api_key_label', 'default');
        return $next($request);
    }
}
```

## 7. OpenAPI SwaggerInfo skeleton

`app/Swagger/SwaggerInfo.php`:

```php
<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: '...',
    title: 'Baokim Xyz API',
    contact: new OA\Contact(name: 'Team Baokim', email: 'team@baokim.vn'),
)]
#[OA\Server(url: '/', description: 'API base URL')]
#[OA\SecurityScheme(securityScheme: 'api_key', type: 'apiKey', name: 'X-API-Key', in: 'header')]

#[OA\Tag(name: 'Xyz v1', description: 'Endpoints chuẩn AC BA')]

#[OA\Post(
    path: '/api/v1/xyz/create',
    summary: 'Tạo Xyz mới',
    security: [['api_key' => []]],
    tags: ['Xyz v1'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/CreateXyzRequest'),
    ),
    responses: [
        new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/XyzV1')),
        new OA\Response(response: 401, description: 'UNAUTHORIZED'),
        new OA\Response(response: 422, description: 'VALIDATION_FAILED'),
    ],
)]

#[OA\Schema(
    schema: 'XyzV1',
    type: 'object',
    properties: [
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'done']),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
    ],
)]

class SwaggerInfo
{
}
```

## 8. Pagination list endpoint

```php
public function list(Request $request): JsonResponse
{
    $limit = max(1, min(100, (int) $request->query('limit', 20)));
    $offset = max(0, (int) $request->query('offset', 0));
    $filters = array_filter([
        'status' => $request->query('status'),
    ]);

    $result = $this->repo->listForHistory($limit, $offset, $filters);

    return response()->json([
        'items' => $result['items']->map(fn ($m) => [...])->all(),
        'limit' => $limit,
        'offset' => $offset,
        'total' => $result['total'],
    ], 200);
}
```

Repository method:

```php
public function listForHistory(int $limit, int $offset, array $filters = []): array
{
    $query = Model::query()->orderByDesc('id');

    foreach (['status', 'doc_type'] as $col) {
        if (! empty($filters[$col])) {
            $query->where($col, $filters[$col]);
        }
    }

    $total = (clone $query)->count();
    $items = $query->limit($limit)->offset($offset)->get();

    return ['items' => $items, 'total' => $total];
}
```
