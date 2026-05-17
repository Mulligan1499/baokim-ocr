# Baokim OCR API

API OCR cho team KSNB (Kiểm soát nội bộ) Baokim — onboarding merchant.
Nhận ảnh/PDF (VI/EN/ZH), trả về text đầy đủ + key-values + bản dịch tiếng Việt + confidence per field + audit trail.

> Dự án cuộc thi **Claude Skill** — Baokim. Sản phẩm OCR pipeline 7 stages, design + implementation đều sinh ra qua skills tại [.claude/skills/](.claude/skills/).

## Kiến trúc — Harness 7 stages

```
Upload → Stage 0 (validation) → queue → Stage 1 (classifier) → Stage 2 (vision extractor)
        → Stage 3 (rule + LLM judge) → Stage 4 (PII masker) → Stage 5 (confidence aggregator)
        → Stage 6 (persist + audit)
```

| Stage | Skill | Model logical | Mục đích |
|---|---|---|---|
| 0 | — | — | Validate file (size/MIME), sha256 dedupe |
| 1 | `ocr-document-classifier` | `classifier` (Gemini 3.1 Pro) | Phân loại 13 doc types + ngôn ngữ |
| 2 | `dev-vision-prompt-designer` | `extractor` (Gemini 3.1 Pro) | Vision OCR → key-values + raw text + translation + confidence per field |
| 3 | `ocr-extraction-validator` | `judge` (Gemini 3.1 Pro) | 3a rule-based regex + 3b LLM-as-judge (independent — chống bias dương) |
| 4 | `vn-pii-masker` | — (regex only) | Mask CCCD/phone/email/MST/account/IP/date/name cho audit + export |
| 5 | `ocr-confidence-aggregator` | — | Weighted formula self×0.3 + rule×0.4 + judge×0.3 → quality bucket + requires_review |
| 6 | — | — | Persist `ocr_extractions` + `ocr_audit_logs` (partition by month — BKM06) |

Provider LLM swap được qua env (`OCR_LLM_PROVIDER=gemini|anthropic`) — interface `LlmClient` ở [app/Services/Llm/](app/Services/Llm/).

## Stack

- PHP 8.3+, Laravel 13
- MySQL 8 (utf8mb4_unicode_ci, prefix `ocr_`)
- Storage: `storage/app/private/ocr/{Y}/{m}/{hash}.{ext}` (FILESYSTEM_DISK swap S3 sau)
- Queue: `database` driver (Redis-ready, không bắt buộc cho dev)
- LLM: Gemini 3.1 Pro (cuộc thi) — sẽ swap sang Claude Sonnet 4.6 + Haiku 4.5 khi Sếp cấp Claude API
- Swagger UI: l5-swagger v11, generate qua PHP 8 attributes

## Endpoints

| Method | Path | Auth | Mục đích |
|---|---|---|---|
| `POST` | `/api/ocr/process` | `X-API-Key` | Upload file → trả `{document_id, status: pending\|processing\|done\|failed}` |
| `GET` | `/api/ocr/{id}` | `X-API-Key` | Lấy kết quả; `?view=raw` (default — KSNB copy-paste) hoặc `?view=masked` (PII đã che) |
| `GET` | `/api/ocr` | `X-API-Key` | List paginated; filter `?status=`, `?per_page=` |
| `GET` | `/api/documentation` | — | Swagger UI |

**Response shape của GET `/api/ocr/{id}` (RAW):**
```json
{
  "document_id": 123,
  "status": "done",
  "extraction": {
    "doc_type": "cccd",
    "language_detected": "vi",
    "page_count": 1,
    "text_full": "<raw OCR text — chứa PII>",
    "key_values": {"so_cccd": "001234567890", "ho_ten": "NGUYỄN VĂN A", "ngay_sinh": "01/01/1990"},
    "translation_vi": null,
    "confidence_overall": 0.94,
    "confidence_per_field": {"so_cccd": 0.97, "ho_ten": 0.95, "ngay_sinh": 0.92},
    "quality": "high",
    "requires_review": false,
    "warnings": [],
    "review_priority_fields": [],
    "view_mode": "raw"
  }
}
```

## RAW vs masked policy

KSNB cần đọc **RAW** (CCCD đầy đủ 12 số) để copy-paste vào hệ thống Baokim + Google Sheet.
Audit logs **luôn** ghi masked (Stage 4 chạy luôn — `text_full_masked`, `key_values_masked` lưu trong DB song song).

| Use case | View |
|---|---|
| KSNB nhập liệu | `GET ?view=raw` (default) |
| Export ra ngoài, gửi audit | `GET ?view=masked` |
| Audit log trong DB | luôn masked |

## Setup local

Tiền điều kiện: PHP 8.3+, Composer 2, MySQL 8 (connect được host từ env), Gemini API key (https://aistudio.google.com/apikey).

```bash
composer install
cp .env.example .env
php artisan key:generate

# fill .env
#   DB_* (MySQL bk_automation)
#   OCR_API_KEY=<32-hex random>
#   OCR_LLM_PROVIDER=gemini
#   GEMINI_API_KEY=<paste key>

php artisan migrate
php artisan l5-swagger:generate
php artisan serve
```

Worker queue (1 terminal riêng):
```bash
php artisan queue:work --queue=default
```

> ⚠️ PHP CLI mặc định `upload_max_filesize=2M` — sẽ reject file PDF/ảnh >2MB trước khi Laravel thấy.
> Khi chạy `artisan serve` hoặc `queue:work`, thêm flag:
> ```bash
> php -d upload_max_filesize=30M -d post_max_size=30M artisan serve --port=8000
> php -d upload_max_filesize=30M -d post_max_size=30M artisan queue:work --queue=default
> ```
> Hoặc sửa vĩnh viễn trong `/opt/homebrew/etc/php/8.5/php.ini`.

## Demo flow (cho Sếp)

1. Mở Swagger UI: http://127.0.0.1:8000/api/documentation
2. Click "Authorize" → paste API key (từ `.env` `OCR_API_KEY`)
3. POST `/api/ocr/process` → upload 1 file CCCD ảnh JPG
4. Lấy `document_id` từ response
5. GET `/api/ocr/{id}` mỗi 3 giây → đến khi `status=done`
6. Xem: `text_full`, `key_values`, `confidence_overall`, `requires_review`, `warnings`
7. So sánh: GET cùng `id` với `?view=masked` → CCCD `001234***890`

Test fixtures gợi ý: CCCD VN ảnh JPG, GPKD VN, English contract PDF, Chinese invoice. Sample upload kèm `docs/postman-collection.json`.

## Cost (Gemini 3.1 Pro)

- 1 doc CCCD đơn ảnh: ~ $0.01-0.03 (3 calls: classifier + extractor + judge)
- 1 doc PDF 5 trang EN có translation: ~ $0.05-0.15
- 1 financial report 20 trang: ~ $0.30-0.60

Volume KSNB thực tế: 600–1200 docs/tháng × ~$0.05 trung bình ≈ **$30–60/tháng**.

Cost guard: env `OCR_DAILY_COST_LIMIT_USD=10` (chưa wired vào middleware — TODO M4).

## Conventions

Tuân thủ 8 rule [.claude/references/baokim-db-standard.md](.claude/references/baokim-db-standard.md):
- **BKM01** — KHÔNG foreign key (validation referential ở Service)
- **BKM02** — Eloquent only, cấm `DB::table()` standalone
- **BKM03** — Repository/Service/Controller 3-layer (xem `app/Services/Ocr/` + `app/Repositories/`)
- **BKM04** — KHÔNG query trong loop
- **BKM06** — `ocr_audit_logs` partition theo tháng (raw SQL trong migration)
- **BKM08** — PII columns flag, KSNB cần RAW nhưng `*_masked` columns lưu song song

## Test suite

```bash
vendor/bin/phpunit          # 32 tests, 76 assertions, ~400ms
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

Integration test ([tests/Feature/Api/OcrPipelineIntegrationTest.php](tests/Feature/Api/OcrPipelineIntegrationTest.php)) mock `LlmClient` để chạy full pipeline 0→6 không cần Internet.

## Future scope (post-competition)

- Word/Excel input (`phpoffice/phpword` + `phpoffice/phpspreadsheet`) — KSNB hiện phải Save As → PDF trước
- PDF text-extract fallback (`smalot/pdfparser`) — text-based PDF không cần vision call (rẻ ~10×)
- Multi-page batch via `Bus::batch()` — hiện chỉ xử lý sequential, cap 20 trang
- Real-time status websocket (Pusher / Laravel Reverb)
- Batch upload 10 file/lần
- Diff 2 extractions (CCCD vs GPKD cross-check người đại diện)
- S3-compatible storage backend (đã sẵn driver, chỉ đổi env)

## Bài học kinh nghiệm (cho slide demo)

- **Skills shape design**: 8 skills trong [.claude/skills/](.claude/skills/) encode tacit knowledge (anti-hallucination patterns, BKM standards, confidence aggregation). Mỗi stage map vào 1 skill — tránh "AI tự nghĩ" tự do, code có khung sườn nhất quán.
- **Skip Claude API ≠ skip Claude Skill**: cuộc thi yêu cầu use **Claude Skill (SKILL.md design pattern)**, không bắt buộc dùng Claude API trong product. Switch sang Gemini cho M2/M3 vì credit Claude chưa được Sếp cấp — nhưng prompt + workflow design vẫn từ skill Claude.
- **Provider abstraction**: bắt đầu code Anthropic-specific, đổi yêu cầu → refactor về `LlmClient` interface. Swap qua env không sửa code. Bài học: prematurely abstract = waste, late abstract = manageable.
- **Anti-hallucination phải có negative few-shot**: Stage 2 prompt encode pattern "smudged CCCD → return `value=""`" trực tiếp trong examples. Không có → model bịa.
- **3-stage confidence > 1**: self-report Stage 2 có bias dương vì cùng model tự chấm. Stage 3a regex + Stage 3b independent judge call kill bias đó. Stage 5 weighted average + critical field cap-low.
- **Partition log table sớm**: BKM06 quan trọng — `ocr_audit_logs` đã partition by month từ đầu. Nếu để sau, ALTER TABLE 1M+ row blocking → downtime.
- **RAW vs masked không phải compliance vs UX trade-off**: tách 2 column. User cần RAW (KSNB copy-paste), audit cần masked. Cost: gấp đôi text storage, lợi: workflow KSNB không gãy + PDPL OK.

## Security gates đã làm

- API key Anthropic + Gemini cách ly trong `.env` (đã trong `.gitignore`)
- `X-API-Key` middleware kiểm constant-time (`hash_equals`)
- File upload sandboxed: max 25MB, MIME allowlist, sha256 dedupe (chống resubmit attack)
- PII masking trong audit logs — production nên thêm DB encryption-at-rest cho `text_full` + `key_values` columns
- ⚠️ Key Anthropic cũ đã lộ trong dev chat — sẽ revoke + rotate khi Sếp cấp key thật
