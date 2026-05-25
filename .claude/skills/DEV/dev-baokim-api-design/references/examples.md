# Examples — Baokim API Design

Reference file lazy-load. 3 case study cụ thể.
Cho 8 quyết định kiến trúc (CRITICAL) → xem [../SKILL.md](../SKILL.md).
Cho templates skeleton → xem [templates.md](templates.md).

## Example 1: OCR API v1 (case study từ dự án này)

**Context**: KSNB Baokim cần API bóc tách tài liệu (ảnh/PDF). BA viết AC document chi tiết 30 mục.

**Decisions theo 8 steps**:

| Step | Quyết định | Lý do |
|---|---|---|
| 1 — Versioning | `/api/v1/ocr/extract`, `/api/v1/ocr/history` | Standard prefix, không version trong header |
| 2 — Error schema | R8 strict 4 field + retry_after khi 503/429 | BA yêu cầu schema chuẩn audit-friendly |
| 3 — Auth | `X-API-Key` static (KSNB nội bộ) | Volume thấp, không cần token rotation |
| 4 — Idempotency | Hash-based 24h (file upload tự nhiên có hash) | KSNB hay re-upload cùng file, tránh cost LLM |
| 5 — Pagination | `limit/offset`, default 20 max 100, mới nhất trước | History list, volume nhỏ ~ thousands |
| 6 — Envelope | Top-level fields, không nested `data` | Response schema BA quy định flat |
| 7 — OpenAPI | l5-swagger v11 + PHP attributes, mount `/api/v1/docs` | KSNB tự test qua Swagger UI |
| 8 — Compat | v1 GA, chưa cần v2 | First release |

**Endpoint contract**:

```
POST /api/v1/ocr/extract
Headers: X-API-Key: <key>
Body: multipart/form-data (file=<binary>)

Response 200:
{
  "request_id": "<uuid>",
  "status": "done",
  "document_id": 17,
  "file_name": "cccd.jpg",
  "file_hash": "<sha256>",
  "uploaded_at": "<iso8601>",
  "cached": false,
  "result": {
    "raw_text": "...",
    "key_value_pairs": [{"key": "so_cccd", "value": "001234567890", "confidence": 0.97, ...}],
    "language_detected": "vi",
    "translation_vi": null,
    "overall_confidence": 0.82,
    "document_type": "cccd",
    "page_count": 1,
    "processed_at": "<iso8601>",
    "ai_model_version": "gemini-2.5-flash",
    "warnings": []
  }
}

Response 413 FILE_TOO_LARGE:
{
  "error_code": "FILE_TOO_LARGE",
  "message_vi": "File vượt quá 10MB.",
  "message_en": "File exceeds 10MB limit.",
  "request_id": "<uuid>"
}
```

**Lessons learned** (đưa vào skill):
- Hash-based idempotency tiết kiệm ~30% cost LLM cho KSNB workflow (re-upload tỷ lệ cao)
- Top-level envelope giảm 30% code parsing ở consumer
- l5-swagger PHP attributes ổn định hơn phpdoc — tránh broken doc khi refactor

## Example 2: Merchant Onboarding API (reusability — chưa build, hypothetical)

**Context**: Baokim cần API tạo merchant mới + upload KYC documents.

**Decisions cùng 8 steps**:

| Step | Quyết định | Khác với OCR? |
|---|---|---|
| 1 | `/api/v1/merchants/{id}/onboarding` | Resource nested |
| 2 | R8 schema | Same |
| 3 | **OAuth Bearer + JWT** thay X-API-Key | Vì có end-user merchant đăng nhập, cần expire |
| 4 | **Idempotency-Key header** thay hash-based | Create operation, không có file hash tự nhiên |
| 5 | Cursor pagination cho list merchants | Sẽ có > 100k merchants, offset deep slow |
| 6 | Top-level fields | Same |
| 7 | OpenAPI same | Same |
| 8 | Audit checklist trước v1 GA | Same |

**Endpoint sample**:

```
POST /api/v1/merchants/{id}/onboarding/submit
Headers:
  Authorization: Bearer <jwt>
  Idempotency-Key: <uuid>
Body: { ... }

Response 200: { ... }
Response 409 IDEMPOTENCY_REPLAY: response cũ
```

**Talking point cho slide thuyết trình**: **Cùng skill `dev-baokim-api-design`, 2 dự án rất khác (OCR vs Onboarding), nhưng đều áp 8 quyết định cùng framework.** Đây là proof reusability cross-project.

## Example 3: Bump version v1 → v2 (audit walkthrough)

**Scenario**: Sau 6 tháng OCR v1 live, KSNB yêu cầu trả thêm field `entities[]` (named entity recognition) + đổi `key_value_pairs[].confidence` từ float [0,1] sang object `{score, calibrated_score}`.

**Audit qua checklist Step 8**:

- [x] Field bị **xóa/đổi type** không?
  - **YES** — `confidence: float` → `confidence: object`. **Breaking change** → buộc bump v2.
- [x] Error code rename? — No
- [x] HTTP status đổi? — No
- [x] Header response xóa? — No
- [x] Auth method đổi? — No
- [x] Default value behavior đổi? — No
- [x] v1 vẫn alive ≥ 6 tháng? — **YES**, plan deprecate 12/2026
- [x] Sunset header chưa? — Thêm vào v1 response: `Sunset: Sat, 31 Dec 2026 23:59:59 GMT`
- [x] Notify consumer? — Email KSNB + Slack #api-changelog 1 tháng trước
- [x] Migration guide? — Viết `docs/api-migration-v1-to-v2.md`

**Path coexistence**:

```
Route::prefix('v1')->group(function () {
    Route::post('/ocr/extract', [OcrV1Controller::class, 'extract']);
    // v1 controller giữ nguyên, chỉ thêm response header Sunset
});

Route::prefix('v2')->group(function () {
    Route::post('/ocr/extract', [OcrV2Controller::class, 'extract']);
    // v2 controller mới, response shape khác
});
```

**Lesson**: bump version **không phải lúc nào cũng cần**. Nếu chỉ add field `entities[]` mà không đổi `confidence` → additive, additive change → KHÔNG cần v2, chỉ document trong v1 CHANGELOG.

## Anti-example: Sai pattern (V0 trước khi refactor)

**Trước AC compliance work**, OCR API v0 có những anti-pattern sau:

```
❌ Route: POST /api/ocr/process    (no /v1/)
❌ Response: { document_id: 17, status: "done", original_name: "..." }  (no request_id UUID)
❌ Error: { error: "Validation failed", details: {...} }  (no error_code, no message_vi/en)
❌ Pagination: list không có total field
❌ No Swagger UI route
```

**Fixed sau khi áp skill `dev-baokim-api-design`**:

```
✅ /api/v1/ocr/extract
✅ Response top-level có request_id UUID
✅ Error theo R8 schema
✅ Pagination { items, limit, offset, total }
✅ Swagger UI tại /api/v1/docs
```

→ Đây là **proof skill được áp dụng** trong dự án OCR. Slide demo có thể show diff trước/sau.
