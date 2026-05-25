# API Spec → Test Case Generation

Khi user upload **OpenAPI/Swagger spec, Postman collection, mô tả API text, hoặc curl example**, áp dụng quy trình sau để generate test case API.

> Reference cơ sở về API testing: xem `platform-api.md`.

---

## 1. Phân loại input

| Loại input | Cách parse |
|---|---|
| **OpenAPI YAML/JSON** (`openapi.yaml`, `swagger.json`) | Parse cấu trúc — paths, methods, parameters, requestBody, responses, schemas |
| **Postman collection** (`*.postman_collection.json`) | Đọc requests, params, examples |
| **cURL command** | Parse method, URL, headers, body |
| **Markdown / text spec** | Đọc tự nhiên, hỏi rõ phần thiếu |
| **Code (controller / route file)** | Đọc để hiểu endpoint, validation, response |

**Quy tắc**: nếu user gửi mô tả ngắn ("API login nhận email password"), **hỏi rõ** request/response schema, status code, error format trước khi generate.

---

## 2. Quy trình phân tích API → test case (8 bước)

### Bước 1 — Lập danh sách endpoint
Liệt kê tất cả endpoint trong scope:
- Method + Path (vd `POST /api/v1/users`)
- Mục đích nghiệp vụ
- Auth required? Role nào?
- Có rate limit không?

### Bước 2 — Cho mỗi endpoint, trích xuất:

| Hạng mục | Cần biết |
|---|---|
| **Path parameters** | Tên, type, format, ví dụ |
| **Query parameters** | Tên, type, required/optional, default, range, enum |
| **Headers** | Required headers (Authorization, Content-Type, X-Request-Id…) |
| **Request body** | Schema, required field, type, format, constraint |
| **Response success** | Status code, schema, example |
| **Response error** | Mọi error code có thể (400, 401, 403, 404, 409, 422, 429, 500), error format |
| **Side effect** | DB ghi gì? Trigger event nào? Gửi notification? |

### Bước 3 — Phân nhóm test case
Mỗi endpoint sinh ra ≥ 5 nhóm test:

1. **Happy path** — request đúng → 2xx + response đúng schema.
2. **Authentication** — không token, token sai, token expire, token revoke.
3. **Authorization** — đúng auth nhưng không có role/permission.
4. **Input validation** — mỗi field một group test (BVA, EP, type mismatch).
5. **Business logic** — rule nghiệp vụ (vd "không được xóa order đã ship").
6. **Concurrency / Idempotency** (POST/PUT/DELETE) — gọi đồng thời, gọi lại.
7. **Error handling** — DB down, external service timeout, malformed JSON.
8. **Security** — IDOR, mass assignment, injection, header manipulation.
9. **Performance** — response time SLA (nếu yêu cầu).

### Bước 4 — Áp dụng kỹ thuật test design
- Field có range (min, max, length) → **BVA**.
- Field có enum → **EP** (mỗi enum value 1 TC + invalid).
- API có nhiều condition kết hợp → **Decision Table**.
- Resource có lifecycle (Order: draft → submitted → paid) → **State Transition** test cho transition API.

### Bước 5 — IDOR check (bắt buộc cho endpoint có ID resource)
Cho mỗi endpoint trả về resource thuộc về user:
- User A có resource X, User B có resource Y.
- User A gọi `GET /resources/X` → 200 ✓
- **User A gọi `GET /resources/Y` → 403 hoặc 404** (KHÔNG được 200).
- Tương tự cho PUT, PATCH, DELETE.

### Bước 6 — Mass assignment check (bắt buộc cho POST/PATCH có body)
- API nhận body có field `email`, `name`. User gửi thêm `is_admin: true`, `email_verified: true`, `created_at: 2020-01-01` → API có ignore không, hay set vào DB?

### Bước 7 — Schema validation
- Response thực tế match schema declared trong OpenAPI.
- Field type đúng (vd `created_at` là string ISO 8601, không phải timestamp number).
- Field optional vắng mặt vẫn parse được client.

### Bước 8 — Generate file Excel
Mỗi test case có format:

| Cột | Nội dung cho API test |
|---|---|
| **Test Item** | "Verify POST /api/users with valid input — return 201" |
| **Pre-Condition** | Auth token, env, DB state nếu cần |
| **Step** | 1. Request method + URL<br>2. Headers (Authorization, Content-Type)<br>3. Body (JSON cụ thể)<br>4. Send request |
| **Expected Output** | - Status code<br>- Response body schema + sample value<br>- DB state sau request<br>- Side effect (event, log) |
| **Priority** | High / Medium / Low |

---

## 3. Test case bắt buộc cho mỗi HTTP method

### POST (Create)
- Happy path → 201 + Location header + body resource mới
- Required field thiếu → 400/422 với field name
- Required field rỗng → 400
- Type mismatch → 400
- Field length boundary
- Field format invalid (email, URL, UUID)
- Duplicate (unique constraint) → 409
- Mass assignment thử inject `id`, `is_admin`, `created_at`
- Idempotency key (nếu có): gọi 2 lần cùng key → kết quả giống nhau
- Auth: không token → 401
- Permission: user không có quyền tạo → 403
- Body rỗng → 400
- Body không phải JSON → 400
- Content-Type sai → 415
- DB ghi đúng record + audit log

### GET (Read — single resource)
- Happy path → 200 + body
- Resource không tồn tại → 404
- ID format sai (vd UUID invalid) → 400
- Auth required → 401 nếu thiếu
- IDOR: user khác sở hữu → 403/404
- Permission: read-only role có được phép không
- Cache header (`ETag`, `Cache-Control`)
- Conditional GET (`If-None-Match`) → 304

### GET (Read — list)
- Happy path → 200 + array
- Empty list → 200 + `[]` (không phải 404)
- Pagination: page=1, page=last, page=middle, page>max
- limit=0, limit=1, limit=max, limit>max
- Sort field hợp lệ, không hợp lệ, asc/desc
- Filter single, multi, không match
- Search: empty, partial, special char
- Date range filter: invalid range (from > to)
- User chỉ thấy data của mình (multi-tenant)
- Performance: 10k record vẫn OK

### PUT / PATCH (Update)
- Happy path → 200 + body updated
- PUT thiếu field required → 400 (PUT yêu cầu full body)
- PATCH chỉ update field gửi → field khác giữ nguyên
- Resource không tồn tại → 404
- Concurrent update — optimistic lock (version mismatch) → 409
- Mass assignment: thử update field protected
- IDOR
- `updated_at` được cập nhật

### DELETE
- Happy path → 204 (no body)
- Resource không tồn tại → 404
- Gọi DELETE 2 lần — lần 2 → 404 hay 204?
- Resource có dependency (vd order chưa ship) → 409 với message rõ
- Soft delete: GET sau DELETE → 404 (hidden) hoặc 200 với `deleted_at`?
- Hard delete: data biến mất hoàn toàn
- IDOR
- Cascade: child resource có bị xóa theo không, đúng kỳ vọng?
- Audit log ghi ai xóa lúc nào

---

## 4. Test case cho các pattern phổ biến

### 4.1 Authentication endpoints (`POST /login`)
- Email + password đúng → 200 + token
- Email đúng, password sai → 401 (message generic, không lộ email tồn tại)
- Email không tồn tại → 401 (cùng message như password sai)
- Email format invalid → 400
- Password rỗng → 400
- Brute force: 5 lần fail → 429 hoặc lockout
- Token trả về có expire time, refresh token (nếu có)
- Login từ device mới → notification email (nếu có)

### 4.2 Token refresh (`POST /auth/refresh`)
- Refresh token hợp lệ → 200 + access token mới
- Refresh token expired → 401
- Refresh token đã dùng (rotation) → 401 + revoke session
- Refresh token revoked (sau logout) → 401
- Multiple refresh đồng thời → tất cả thành công hoặc race condition handled

### 4.3 File upload (`POST /upload`)
- File hợp lệ → 200 + URL/ID
- File rỗng → 400
- File quá lớn (vượt max size) → 413
- File type không cho phép → 400
- Multi-part form data đúng format
- Filename có special char, Unicode, đường dẫn (`../../../etc/passwd`)
- Upload đồng thời 2 file → 2 ID khác nhau
- Network ngắt giữa upload → resume hay restart
- Virus / malware detection (nếu có)

### 4.4 Search / Filter API
- Empty query → trả về tất cả hay 400?
- Special char (SQL injection, regex injection)
- Unicode, emoji
- Very long string
- Multi-criteria filter
- Pagination + filter combine

### 4.5 Webhook receiver
- Payload đúng signature → 200
- Signature sai → 401
- Replay attack: cùng payload gửi 2 lần → idempotent
- Timeout từ caller: caller retry → server xử lý đúng
- Out-of-order event → server xử lý đúng (theo timestamp)

### 4.6 Bulk operation (`POST /users/bulk`)
- Tất cả valid → 200 + summary
- Một phần valid → 207 Multi-Status với detail từng item
- Tất cả invalid → 400 hay 207?
- Vượt max bulk size → 413/422
- Transaction: tất cả thành công hoặc rollback?

---

## 5. Security test bắt buộc (OWASP API Top 10)

Cho mỗi endpoint, **luôn** thêm test:

| OWASP API | Test |
|---|---|
| **API1 BOLA** (IDOR) | User A truy cập resource user B → 403/404 |
| **API2 Auth** | Token sai/expire/revoke → 401 |
| **API3 BOPLA** (Mass assignment) | Inject field nhạy cảm → bị ignore |
| **API4 Resource** | Request không có pagination → trả về toàn bộ DB? |
| **API5 BFLA** | User thường gọi admin endpoint → 403 |
| **API6 Sensitive flow** | Rate limit cho login, OTP, transfer money |
| **API7 SSRF** | Field nhận URL → trỏ vào `http://localhost:8080`, `http://169.254.169.254/` |
| **API8 Misconfiguration** | CORS `*`, debug header, error stack trace |
| **API9 Inventory** | API v1 vẫn live khi đã có v2 |
| **API10 Unsafe consumption** | API gọi 3rd party có validate response không |

---

## 6. Ví dụ end-to-end

**Input** (user gửi spec text):
```
POST /api/v1/orders
Headers:
  Authorization: Bearer <token>
  Content-Type: application/json

Body:
  {
    "items": [{"product_id": "uuid", "quantity": int (1-100)}],
    "shipping_address": "string (max 500)",
    "payment_method": "card | cod | bank_transfer"
  }

Response 201:
  { "id": "uuid", "total": decimal, "status": "pending" }
Response 400: validation error
Response 401: no auth
Response 403: insufficient quota
Response 409: out of stock
```

**Sections sinh ra**:

| Section | TC count |
|---|---|
| Happy path | 4 (mỗi payment_method + multi-item) |
| Validation: items array | 6 (rỗng, > 50 item, product_id invalid, quantity 0, quantity 101, type wrong) |
| Validation: product_id | 4 (UUID invalid, không tồn tại, soft-deleted, sản phẩm inactive) |
| Validation: quantity | 5 (0, 1, 100, 101, negative) |
| Validation: shipping_address | 5 (rỗng, 1 char, 500, 501, special char) |
| Validation: payment_method | 4 (3 enum + invalid) |
| Auth | 4 (no token, sai, expired, revoked) |
| Authorization | 2 (user thường, user banned) |
| Business logic — out of stock | 3 (1 item hết, multi-item 1 hết, race condition) |
| Business logic — quota exceed | 2 |
| Idempotency | 2 (gọi lại với cùng key, không key) |
| Concurrency | 2 (2 user đặt sản phẩm cuối) |
| Mass assignment | 3 (inject status, total, user_id) |
| IDOR | 2 |
| Error handling | 3 (DB down, payment service timeout, invalid JSON) |
| Security | 4 (SQL injection trong product_id, XSS trong address, SSRF, CORS) |
| **Total** | **~55 TC** |

→ Output: file Excel `Order_API_TestCases.xlsx`, module_code = "API_ORDER".

---

## 7. Anti-pattern

| Anti-pattern | Sửa |
|---|---|
| Chỉ test happy path 200 | Bắt buộc cover mọi error code có thể |
| Test schema bằng cách hardcode trong test | Dùng OpenAPI validator (Schemathesis, Dredd) |
| Test data có UUID hardcoded | Dùng fixture, factory hoặc seed script |
| Step ghi "gọi API" mà không có cURL/example | Step phải có method + URL + headers + body cụ thể |
| Expected: "trả về thành công" | Phải có status code + schema + DB state cụ thể |
| Bỏ qua IDOR vì "đã có middleware auth" | Vẫn phải test cho mỗi endpoint |
| Không test mass assignment | Đây là bug bảo mật phổ biến nhất API hiện nay |
