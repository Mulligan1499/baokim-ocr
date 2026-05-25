---
name: dev-baokim-api-design
description: Skill design REST API theo Baokim standards — cover 8 quyết định kiến trúc (versioning, error schema R8, auth, idempotency, pagination, response envelope, OpenAPI, backward compat). Output route + Controller + Resource + Request validation + checklist.
when_to_use: |
  Triggers: "thiết kế API cho [feature]", "API contract Baokim", "design REST endpoint",
  "API versioning Baokim", "error schema chuẩn", "idempotency strategy",
  "review API design này", "OpenAPI annotation cho [endpoint]", "thiết kế API v1",
  "pagination strategy API", "API authentication Baokim".

  Anti-triggers (KHÔNG dùng skill này khi):
  - GraphQL/gRPC API (skill này REST-specific)
  - Internal RPC giữa service (không phải public API)
  - Pure schema design không có endpoint → dùng `dev-baokim-db-schema-designer`
  - API performance optimization query → dùng `dev-baokim-sql-reviewer`
  - Generic REST best practice không Baokim-specific (web search đủ)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: dev
created: 2026-05-21
updated: 2026-05-25
tags: [dev, api-design, rest, baokim, versioning, idempotency, openapi, error-handling, backward-compat]
---

# Baokim REST API Design Standards

## Mục đích

Skill encode 8 quyết định kiến trúc Baokim **đã battle-tested** trong dự án OCR KSNB v1 (5/2026). Giúp dev:

- Thiết kế API v1 lần đầu **không cần review nhiều vòng** — convention đã định sẵn
- Tránh breaking change cho client downstream (KSNB nội bộ + đối tác merchant)
- Match error schema mà BA expect → pass UAT ngay lần đầu
- OpenAPI tự động accurate, dev không phải maintain doc tay

**Reference foundation**:
- `docs/AC_COMPLIANCE.md` của dự án OCR (R1-R8 business rules từ BA)
- `dev-baokim-laravel-repo-pattern` (BKM03 3-layer — controller mỏng, service béo)

## Khi nào dùng skill này

✅ Thiết kế API endpoint mới cho dự án Baokim
✅ Review API contract trước khi BA approve
✅ Bump version v1 → v2, cần audit backward compat
✅ Onboard dev mới vào dự án có sẵn API — đọc skill này hiểu convention
✅ Build proof-of-concept để demo Sếp/khách

❌ GraphQL hoặc gRPC API (skill này REST-only)
❌ Internal RPC giữa các service Baokim (không cần versioning strict)
❌ Schema design pure không có endpoint (dùng `dev-baokim-db-schema-designer`)
❌ Performance review (dùng `dev-baokim-sql-reviewer` cho query layer)

## Workflow — 8 quyết định kiến trúc

Đi tuần tự, **không skip bước nào**. Mỗi bước có default + escape hatch để justify nếu chọn khác.

### Step 1 — URL Versioning

**Convention**: `/api/v{N}/<resource>/<action>` — version trong URL path, **KHÔNG** trong header.

```
POST /api/v1/ocr/extract             ✅
GET  /api/v1/ocr/history/{id}        ✅
POST /api/extract (no version)       ❌ — không tracking được version
POST /v1/ocr/extract (no /api)       ❌ — conflict với web routes
GET  /api/ocr/extract?version=1      ❌ — version trong query bị cache fragmented
```

**Bump version khi nào**:
- Breaking change response schema (rename/remove field, change type)
- Breaking change auth/error format
- Behavior change cho cùng request (vd: idempotency window khác)

**KHÔNG bump khi**:
- Thêm field mới vào response (additive — clients ignore unknown)
- Thêm endpoint mới
- Fix bug không đổi contract
- Đổi internal implementation (DB, LLM provider, queue)

### Step 2 — Error Schema (R8 chuẩn)

**Mọi error response** (4xx, 5xx) **phải** theo schema:

```json
{
  "error_code": "FILE_TOO_LARGE",
  "message_vi": "File vượt quá 10MB.",
  "message_en": "File exceeds 10MB limit.",
  "request_id": "uuid-v4",
  "retry_after": 60
}
```

**Rules**:
- `error_code`: `SCREAMING_SNAKE_CASE`, stable contract — clients switch/case theo này (vd `FILE_TOO_LARGE`, `EMPTY_FILE` — tương tự convention TINYINT enum comment values per **SCR04.2**)
- `message_vi`: 1 câu tiếng Việt cho end-user (KSNB nội bộ đọc)
- `message_en`: 1 câu tiếng Anh cho dev/log
- `request_id`: UUID v4 — column lưu `request_id CHAR(36) UNIQUE` per **SCR02.6** (CHAR cho fixed-length), **luôn có** kể cả endpoint không lookup được (sinh fresh nếu cần)
- `retry_after`: optional, **chỉ** khi 503/429 — số giây

**HTTP status semantics Baokim**:

| Status | Khi nào |
|---|---|
| 200 | OK + body có data |
| 202 | Async accepted, poll sau qua request_id |
| 400 | Input rõ ràng sai (`INVALID_FILE_FORMAT`, `EMPTY_FILE`) |
| 401 | Thiếu/sai credential — **kèm header `WWW-Authenticate`** |
| 404 | Resource không tồn tại |
| 413 | Payload quá lớn (`FILE_TOO_LARGE`) |
| 422 | Validation Laravel fail (`MISSING_FIELD`) |
| 429 | Rate limit — kèm `Retry-After` |
| 503 | Vendor (LLM/DB/Redis) down — kèm `Retry-After` |

**Helper class**: dùng `App\Support\ApiErrorResponse::make($code, $vi, $en, $status, $retryAfter, $requestId)` — không inline `response()->json()`.

### Step 3 — Authentication

**Default cho Baokim**: `X-API-Key` static header.

```
X-API-Key: <random 32 hex chars>
```

**Rules**:
- Key trong **HEADER**, không query param (log/CDN leak)
- Storage: `.env` (`OCR_API_KEY=...`), **không hardcode** trong code
- 1 key per consumer label (KSNB nội bộ = 1 key, merchant đối tác = 1 key khác)
- Verify bằng `hash_equals()`, không `===` (timing attack)
- 401 response phải có header `WWW-Authenticate: ApiKey realm="<service-name>"`

**Khi nào dùng Bearer/OAuth thay X-API-Key**:
- Public API có end-user login (KSNB onboarding portal cho merchant)
- Cần expire/rotate token thường xuyên
- Cần scope/permission per token

**KHÔNG** dùng Bearer khi:
- Internal/B2B nội bộ — X-API-Key đủ
- Volume thấp (< 10k req/ngày)

### Step 4 — Idempotency

**2 strategy, pick 1 per endpoint**:

#### A. Hash-based (cho file upload / read-heavy)
- Hash request body (sha256) làm dedup key
- Cache window: 24 giờ default (`OCR_IDEMPOTENCY_HOURS=24`)
- Cùng hash trong window → return same result + flag `cached: true`
- Implement: column `cached_until` trong table chính + repository method `findCachedByHash()`

#### B. `Idempotency-Key` header (cho create operations)
- Client gửi `Idempotency-Key: <uuid>` header
- Server store `(idempotency_key, response)` trong Redis 24h
- Cùng key trong window → return cached response
- Khác key → run lại (client có thể retry với key mới)

**Decision tree**:
```
Endpoint type → strategy
─ Upload file lớn (OCR, document) → Hash-based (tự nhiên dedup)
─ Create resource (đăng ký merchant) → Idempotency-Key header
─ GET / read endpoint → Không cần (idempotent by HTTP semantics)
─ Update / delete → Idempotency-Key header
```

### Step 5 — Pagination

**Default**: `limit` + `offset` query param.

```
GET /api/v1/ocr/history?limit=20&offset=40
```

**Rules**:
- `limit` default 20, max 100 (cap server-side, không tin client)
- `offset` default 0
- Response phải có `{ items, limit, offset, total }`
- Order: **mới nhất trước** (`ORDER BY id DESC` hoặc `created_at DESC`)
- Nếu filter có `IN (...)` (vd `?ids=1,2,3,...`) → **cap ≤ 500 params** per **SQR02.2** — vượt sẽ break query

**Khi nào dùng cursor pagination thay offset (theo SQR01.2)**:
- Table > 100k rows (offset deep slow — `LIMIT 50000, 100` scan 50100 rows)
- Real-time feed có insert liên tục (offset bị shift)
- Frontend infinite scroll
- **Per SQR01.2**: seek pagination `WHERE id > last_id ORDER BY id LIMIT 20` thay vì `LIMIT offset, 20`

**Cursor format Baokim**: base64-encode `{"after_id": 12345}`, NOT raw timestamp/id (tránh client guess). Cursor column phải có index (idx_id sẵn từ PK, idx_created_at nếu cursor theo timestamp).

### Step 6 — Response Envelope

**Success response** — KHÔNG nhét data vào field generic `data` envelope. Trả thẳng resource ở top level:

```json
// ✅ Good
{
  "request_id": "...",
  "status": "done",
  "result": { ... }
}

// ❌ Bad (rườm rà)
{
  "success": true,
  "data": {
    "request_id": "...",
    ...
  },
  "errors": null
}
```

**Lý do**:
- Client parse trực tiếp, không cần `.data.data.field`
- Error path đã có schema riêng (Step 2)
- Lightweight, không duplicate metadata

**Exception**: pagination list → cần envelope `{ items, limit, offset, total }` vì cần metadata.

### Step 7 — OpenAPI Annotation (l5-swagger v11+)

**Convention Baokim**: dùng **PHP 8 attributes** `#[OA\...]`, **không phpdoc** `@OA\...`.

```php
#[OA\Post(
    path: '/api/v1/ocr/extract',
    summary: 'Tóm tắt 1 câu',
    description: 'Mô tả dài + AC reference',
    security: [['api_key' => []]],
    tags: ['OCR v1'],
    requestBody: new OA\RequestBody(...),
    responses: [
        new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/...')),
        new OA\Response(response: 400, description: 'Validation failed'),
    ],
)]
```

**Rules**:
- 1 file `App\Swagger\SwaggerInfo.php` chứa **tất cả** annotation — controller sạch
- Schema components dùng `#[OA\Schema(schema: 'V1Document', ...)]` tách riêng để reuse
- Swagger UI mount tại `/api/v{N}/docs` (per version)
- Auto-generate qua `php artisan l5-swagger:generate` trong CI

### Step 8 — Backward Compatibility Audit (bump version)

Trước khi merge bump version v1 → v2, audit checklist:

- [ ] List **tất cả response field** của v1 — có field nào **xóa hoặc đổi type** không?
- [ ] Error code có bị rename không?
- [ ] HTTP status code có đổi không (vd 200 → 202)?
- [ ] Header response có header nào bị xóa?
- [ ] Auth method có đổi (X-API-Key → Bearer)?
- [ ] Default value behavior có đổi (vd `limit=20` → `limit=50`)?
- [ ] v1 endpoint vẫn alive **ít nhất 6 tháng** sau khi v2 GA?
- [ ] Có sunset header chưa? `Sunset: Sat, 31 Dec 2026 23:59:59 GMT`
- [ ] Đã notify consumer chưa? (KSNB email + Slack #api-changelog)
- [ ] Migration guide v1→v2 đã viết chưa? (`docs/api-migration-v1-to-v2.md`)

**Nguyên tắc**: **deprecation > deletion**. v1 chạy song song với v2 ≥ 6 tháng, dù v2 đã better.

## Templates & Examples (lazy-load references)

- **Templates skeleton** (route file + controller + Resource + Request + ApiErrorResponse helper + OpenAPI annotation): xem [references/templates.md](references/templates.md)
- **Case study OCR v1** (full walkthrough 3 endpoint với schema, audit log, idempotency) + cross-domain HR API example: xem [references/examples.md](references/examples.md)

Đọc references **khi cần adapt cho endpoint cụ thể**. Cho 8 quyết định kiến trúc (CRITICAL) → đã có ở SKILL.md này.

## What NOT to do

❌ KHÔNG version trong header (`API-Version: v1`) — URL path rõ ràng + cacheable
❌ KHÔNG trộn 2 schema error khác nhau trong cùng API (vd v1 routes dùng `{error, details}`, v2 dùng R8) — confuse client
❌ KHÔNG return 200 cho error case "soft fail" — dùng đúng HTTP status. 200 phải = success
❌ KHÔNG hardcode API key trong code/test — `.env` only, test inject qua `config(['ocr.api_key' => ...])`
❌ KHÔNG dùng auto-increment ID làm public identifier — leak count + brute-forceable. Dùng UUID `request_id`
❌ KHÔNG để Swagger UI public anonymous trong Production — wrap auth middleware (dev/staging có thể mở)
❌ KHÔNG bump version cho additive change — chỉ bump khi breaking
❌ KHÔNG xóa endpoint v1 ngay khi v2 GA — minimum 6 tháng deprecation window
❌ KHÔNG dùng `$request->all()` rồi `$model::create(...)` — luôn FormRequest validate trước

## Decision shortcut — câu hỏi nhanh khi review API

1. URL có `/api/v{N}/` không? Nếu không → fail
2. Error response có đủ 4 field `error_code/message_vi/message_en/request_id`? Nếu không → fail
3. Có endpoint nào return 200 cho error case? Nếu có → fail
4. Auth verify dùng `hash_equals()` không? Nếu `===` → fail
5. List endpoint có cap `limit` max 100 server-side? Nếu không → fail
6. OpenAPI annotation có chưa? Run `php artisan l5-swagger:generate` không lỗi → pass
7. Backward compat audit (nếu bump version) — checklist 10 items đủ chưa?

## Maintenance

- Version bump skill khi: Baokim ra convention mới (vd thêm Tracing header), breaking change OpenAPI tooling
- Update khi: dự án mới phát hiện anti-pattern + cách fix → add vào "What NOT to do"
- Source of truth cho R1-R8 + AC pattern: dự án OCR `docs/AC_COMPLIANCE.md`
- Khi BA team Baokim release Style Guide mới → sync skill này
