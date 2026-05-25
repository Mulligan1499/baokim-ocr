# API Testing — Checklist & Strategy

Áp dụng cho REST API, GraphQL, gRPC, WebSocket. Trọng tâm: contract correctness, security, resilience.

---

## 1. Tầng test API và sự khác biệt

| Tầng | Mục đích | Tool |
|---|---|---|
| **Unit test** (dev viết) | 1 function/method | JUnit, Jest, pytest |
| **Component test** | 1 service in-memory, mock DB/external | Test framework + mock |
| **Contract test** | API match với consumer expectation | Pact, Spring Cloud Contract |
| **Integration test** | API + DB thật + external mock | Postman, RestAssured |
| **E2E API test** | Full flow qua nhiều API | Postman collection, Karate |

QA tập trung vào **Contract, Integration, E2E**.

---

## 2. Functional API Testing

### 2.1 Happy Path — bắt buộc cho mỗi endpoint:

- Request đúng → status 200/201, response đúng schema, đúng data.
- Header `Content-Type` đúng.
- Field trong response đúng kiểu (string, number, boolean, null).
- Trường optional vắng mặt vẫn parse được.
- Datetime format thống nhất (ISO 8601: `2024-01-15T10:30:00Z`).

### 2.2 Validation — bắt buộc:

#### Required field:
- Thiếu field → 400 với message rõ ràng (chỉ ra field nào).
- Field rỗng `""` vs `null` vs missing — server xử lý nhất quán.

#### Format:
- Email: `not-an-email`, `a@b`, `@b.com`, `a@`, `a@b.c` (TLD 1 ký tự).
- Phone: định dạng quốc tế, có/không có dấu +, có space.
- URL: thiếu schema, javascript:, ftp://.
- UUID: invalid format.
- Date: `2024-13-45`, `2024-02-30`, timezone không có/sai.

#### Range:
- Min/max numeric, min/max length string, min/max array size.
- Negative number cho field positive-only.
- Số rất lớn (overflow Int32, Int64).
- Decimal precision (vd field tiền tệ 2 chữ số thập phân, gửi 3).

#### Enum:
- Giá trị không thuộc enum → 400.
- Case sensitivity: `"PENDING"` vs `"pending"` — convention rõ ràng.

#### Type mismatch:
- Số gửi dưới dạng string: `{"age": "25"}` — accept hay reject?
- Boolean gửi `"true"` (string), `1`, `0`, `"yes"`.
- Array thay vì single value, ngược lại.

### 2.3 HTTP Method correctness:

- GET không có side effect (idempotent + safe).
- POST tạo mới — gọi 2 lần tạo 2 record (trừ khi có idempotency key).
- PUT thay thế toàn bộ resource.
- PATCH thay thế partial — chỉ update field gửi lên.
- DELETE — gọi lần 2 trên record đã xóa: 404 hay 204?
- Method không support → 405 Method Not Allowed + header `Allow`.

### 2.4 Status Code đúng convention:

| Code | Khi nào |
|---|---|
| 200 | GET/PUT/PATCH thành công, có body |
| 201 | POST tạo thành công, có body resource mới (kèm `Location` header) |
| 202 | Accepted nhưng chưa xử lý xong (async) |
| 204 | DELETE thành công, không body |
| 400 | Bad request — input không hợp lệ |
| 401 | Chưa authenticate |
| 403 | Đã authenticate nhưng không có quyền |
| 404 | Resource không tồn tại |
| 409 | Conflict (vd duplicate, optimistic lock) |
| 422 | Unprocessable entity (validation fail có cấu trúc) |
| 429 | Rate limit exceeded |
| 500 | Server error (không bao giờ được trả vì input của user) |
| 503 | Service unavailable / maintenance |

**Bug điển hình**: trả 200 + `{"error": "..."}` thay vì 4xx → consumer không phát hiện lỗi.

### 2.5 Pagination:

- Cursor-based hay offset-based — consistent across endpoints.
- Trang đầu, trang cuối, trang giữa, trang vượt range.
- `limit=0`, `limit=-1`, `limit=10000` (max limit).
- Total count đúng.
- Sort kết hợp với pagination — không skip/duplicate khi có insert/delete giữa các request.

### 2.6 Sort & Filter:

- Sort field hợp lệ, không hợp lệ.
- Sort asc/desc, nhiều field.
- Filter null vs missing param.
- Filter kết hợp (AND, OR).
- Special character trong filter value (escape đúng).

---

## 3. Authentication & Authorization

### 3.1 Auth scheme:
- API Key, Bearer token (JWT), OAuth 2.0, Basic Auth, mTLS.
- Token expire — hành vi sau khi expire (401 + nội dung response).
- Refresh token flow (nếu có): rotation, revoke khi logout.

### 3.2 Test cases bắt buộc:
- Không có token → 401.
- Token sai format → 401.
- Token expired → 401.
- Token revoked → 401.
- Token đúng nhưng signature sai → 401.
- Token đúng user nhưng không có role → 403.
- Token user A truy cập resource user B (IDOR) → 403/404.

### 3.3 IDOR & Access Control — test riêng cho từng endpoint:

```
Setup: User A có resource X, User B có resource Y.

Test:
- GET /resources/X với token A → 200 (data X)
- GET /resources/Y với token A → 403 hoặc 404 (KHÔNG được 200)
- GET /resources/Y với token B → 200
- GET /resources/9999 (không tồn tại) → 404
- PUT /resources/Y với token A → 403
- DELETE /resources/Y với token A → 403
```

**Lưu ý**: trả 404 thay vì 403 khi không có quyền là **best practice security** (không tiết lộ resource có tồn tại hay không) — consistent một cách.

---

## 4. Data Integrity & Concurrency

### 4.1 Idempotency:
- API có idempotency key (POST tạo order, payment): gọi 2 lần cùng key → kết quả giống nhau, không tạo 2 record.
- Test với network retry (network glitch khiến client retry).

### 4.2 Concurrency:
- 2 request cùng update 1 record → optimistic lock (version mismatch → 409) hay last-write-wins?
- Race condition: 2 request POST tạo cùng resource có unique constraint → 1 success + 1 conflict 409.
- Inventory: 2 request đặt đơn cuối cùng — atomicity qua DB transaction.

### 4.3 Transaction rollback:
- Tạo order với invalid item → cả order rollback, không partial.
- Lỗi giữa chừng (DB down sau khi ghi 1 phần) → state DB consistent.

---

## 5. Error Handling

### 5.1 Error response format — consistent:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Email format invalid",
    "details": [
      { "field": "email", "issue": "INVALID_FORMAT" }
    ],
    "trace_id": "abc-123"
  }
}
```

**Test**:
- Mọi error response cùng schema.
- Error code (string) ổn định để client switch case.
- Error message không lộ stack trace, không lộ SQL, không lộ path.
- Có `trace_id` để debug.
- Multi-language error message (nếu API có locale).

### 5.2 Edge case khi hệ thống xuống:
- DB down → 503 với message rõ, không crash.
- External API timeout → có fallback hay 504.
- OOM, full disk → graceful degradation.

---

## 6. Performance & Resilience

### 6.1 Performance API:
- **Response time** P50/P95/P99 trong SLA (vd P95 < 500ms).
- **Throughput**: req/s tối đa trước khi degrade.
- **Concurrency**: 100 user đồng thời, 1000 user đồng thời.
- Test bằng k6, JMeter, Locust, Gatling.

### 6.2 Resilience:
- Rate limiting: vượt limit → 429 + header `Retry-After`.
- Circuit breaker: external service down → fail fast.
- Retry logic: client retry không gây tạo data trùng.
- Timeout hợp lý: API nhanh < 30s, async > 30s thì dùng job queue.

### 6.3 Caching:
- `Cache-Control`, `ETag`, `Last-Modified` đúng.
- Conditional request: `If-None-Match` → 304 nếu chưa đổi.
- Cache invalidation đúng khi data update.

---

## 7. Schema & Contract Testing

### 7.1 OpenAPI/Swagger validation:
- Response thực tế match với schema declared.
- Tool: Schemathesis (auto-generate test từ OpenAPI), Dredd.

### 7.2 Backward compatibility:
- Thêm field mới optional → consumer cũ vẫn hoạt động.
- Xóa field, đổi type, đổi enum value → **breaking change**, cần versioning (`/v1/`, `/v2/`).
- Test: chạy test suite của consumer cũ với API mới.

### 7.3 Contract testing với Pact:
- Consumer định nghĩa expectation → Pact file.
- Provider verify Pact file → fail nếu API thay đổi mà chưa update consumer.

---

## 8. GraphQL-specific

- **Query depth limit**: gửi query lồng 20 cấp → reject (tránh DoS).
- **Query complexity**: query lấy 1 triệu record → reject.
- **Introspection**: tắt trên production.
- **Field-level authorization**: user có thể query type nhưng không được xem field nhạy cảm.
- **N+1 query**: dataloader có hoạt động không, đo qua DB log.
- **Mutation idempotency**: tương tự REST POST.

---

## 9. WebSocket & Real-time

- Connect, disconnect, reconnect (auto khi mất mạng).
- Authentication: token trong handshake.
- Message ordering: nhận đúng thứ tự gửi.
- Backpressure: client chậm nhận → server có drop hay buffer hay disconnect?
- Heartbeat / ping-pong: detect disconnect.
- Concurrent connection limit per user.

---

## 10. Security API Testing

### 10.1 OWASP API Security Top 10 (2023):

1. **BOLA** (Broken Object Level Authorization) — IDOR (xem mục 3.3).
2. **Broken Authentication** — token weak, không expire, brute-force.
3. **BOPLA** (Broken Object Property Level Authorization) — user update field họ không nên update (vd `is_admin`).
4. **Unrestricted Resource Consumption** — không có rate limit, request không có pagination, query phức tạp.
5. **BFLA** (Broken Function Level Authorization) — user thường gọi API admin.
6. **Server Side Request Forgery** — API nhận URL để fetch → user trỏ vào internal IP (`http://localhost:8080`, `http://169.254.169.254/`).
7. **Security Misconfiguration** — CORS quá rộng (`*`), debug header.
8. **Lack of Protection from Automated Threats** — credential stuffing, scraping.
9. **Improper Inventory Management** — API v1 cũ vẫn live, staging endpoint trên production.
10. **Unsafe Consumption of APIs** — trust 3rd party API mà không validate.

### 10.2 Common security tests:

- **Mass assignment**: PATCH `/users/123` với `{"is_admin": true}` — server có cho update không?
- **Parameter pollution**: `?role=user&role=admin` — server lấy giá trị nào.
- **HTTP method override**: header `X-HTTP-Method-Override: DELETE` → có bypass auth không.
- **CORS**: preflight OPTIONS đúng, `Access-Control-Allow-Origin` không quá rộng.
- **Injection trong header**: `User-Agent: <script>...`, header có log → log injection.

---

## 11. Test Data Management

- Sử dụng **fixture** ổn định cho regression.
- **Cleanup** sau mỗi test (DELETE, hoặc test trong transaction rollback).
- **Isolated environment** — không chạy test trên production.
- **Mask PII** khi copy production data về staging.
- **Versioned test data** — track theo Git.

---

## 12. Tools đề xuất

| Loại | Tool |
|---|---|
| Manual exploration | Postman, Insomnia, Bruno, Hoppscotch |
| Automation | RestAssured (Java), Karate, Postman/Newman, Pytest + requests, supertest (Node) |
| Performance | k6, JMeter, Locust, Gatling |
| Contract | Pact, Spring Cloud Contract |
| Schema validation | Schemathesis, Dredd, ajv |
| Security scan | OWASP ZAP API scan, Burp Suite, 42Crunch |
| Mock server | WireMock, Prism, MSW |
| Monitoring | Postman Monitors, Pingdom, Datadog Synthetic |

---

## 13. Bug điển hình của API (luôn check)

1. Trả 200 với body lỗi thay vì 4xx.
2. Trường `password` lộ trong response.
3. Trường `id` user khác lộ trong response.
4. PATCH cho phép update field không nên (`is_admin`, `email_verified`).
5. Pagination không deterministic khi có insert.
6. Không validate Content-Type (gửi XML vào JSON endpoint).
7. Datetime trả về local time của server thay vì UTC.
8. `null` vs `""` vs missing không nhất quán.
9. Error message khác nhau giữa các endpoint cho cùng loại lỗi.
10. CORS `*` trên production.
11. Rate limit thiếu trên endpoint quan trọng (login, signup, OTP).
12. JWT không có expire, hoặc expire quá dài (30 ngày).
13. Endpoint admin không có authorization check.
14. SQL injection qua query param tưởng đã được parameterized.
15. Date filter không xử lý timezone client.
