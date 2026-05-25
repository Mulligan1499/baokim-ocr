# Spec Audit Checklist

Khi đọc 1 API spec, đi qua checklist sau và record ra audit table TRƯỚC KHI viết code.

## 1. Endpoint metadata
- [ ] Method (GET/POST/PUT/PATCH/DELETE)
- [ ] URL pattern (có path param không?)
- [ ] Auth method (Bearer / X-API-Key / Basic / OAuth2)
- [ ] Content-Type request (application/json / multipart/form-data / x-www-form-urlencoded)

## 2. Request schema
- [ ] Header bắt buộc?
- [ ] Body field bắt buộc + optional?
- [ ] Query param bắt buộc + optional?
- [ ] Type của mỗi field?
- [ ] Constraint (min/max length, regex, enum)?
- [ ] File upload: size limit? MIME whitelist? page limit (PDF)?

## 3. Response 200 schema
- [ ] Top-level required fields → ghi đủ vào audit table
- [ ] Nested objects? → schema riêng cho mỗi cấp
- [ ] Array items? → schema riêng cho item
- [ ] Optional fields → điều kiện nào trả về?
- [ ] Cross-field rule? (vd `result.request_id` phải = top-level `request_id`)

## 4. Format & enum
- [ ] UUID v4 / v3 / v5?
- [ ] Timestamp ISO 8601 có timezone?
- [ ] Hash algorithm (SHA-256 = 64 hex chars)?
- [ ] Enum đóng (strict) hay mở (kèm "...")? → strict thì List Should Contain Value; mở thì cho phép extend
- [ ] Number range (vd confidence 0-1)?

## 5. Business rules / formulas
- [ ] Có công thức tính (weighted, average, threshold) không?
- [ ] Coefficient cụ thể? (vd critical field weight=2)
- [ ] Conditional logic? (vd confidence <0.5 → unknown)
- [ ] Tolerance cho phép?
- [ ] Có response example trong spec để sanity check công thức?

## 6. Error response schema
- [ ] Schema chuẩn (error_code, message, retry_after, ...)?
- [ ] Enum error_code đầy đủ?
- [ ] HTTP status code mapping (400 → INVALID_FILE, 413 → TOO_LARGE, ...)?

## 7. Cache / idempotency
- [ ] Có cache không? Scope (per user, per file_hash, per session)?
- [ ] TTL?
- [ ] Field `cached` trong response?

## 8. Audit log / observability
- [ ] Có ghi audit log không?
- [ ] Field mask PII?

## 9. Security
- [ ] HTTPS only?
- [ ] CORS whitelist?
- [ ] Rate limit?
- [ ] RBAC (user A không xem được data user B)?
- [ ] Mass assignment protection?

## 10. Gap & conflict
- [ ] Spec có chỗ nào mơ hồ / mâu thuẫn? → đánh dấu `[GAP-XX]` và list ra confirm với BA
- [ ] Có trade-off đã chốt? → đánh dấu `[TRADE-OFF #X]` để skip TC tương ứng

---

## Template audit table

Sau khi đi qua checklist, ghi lại dạng:

```markdown
| Path | Required | Type | Format/Enum | Note |
|---|---|---|---|---|
| request_id | ✓ | string | UUID v4 | top-level |
| status | ✓ | string | {done, processing, failed} | enum đóng |
| result.overall_confidence | ✓ | number 0-1 | weighted avg | R3 formula |
| result.translation_vi | optional | string | | chỉ khi language != vi |
| result.warnings | optional | array<string> | mảng rỗng = [] | nếu không có cảnh báo |
```

Dùng table này làm input để generate `Verify <Endpoint> Response Schema`.
