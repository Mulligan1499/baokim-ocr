# Baokim OCR API

> **Cuộc thi Claude Skill 2026 — Baokim**
> API OCR cho team KSNB (Kiểm soát nội bộ) Baokim — onboarding merchant.
> Nhận ảnh/PDF (Việt/Anh/Trung) → trả text đầy đủ + key-value pairs + bản dịch tiếng Việt + confidence per field + audit trail.

---

## 🎯 Cho BGK đọc trước

**Skill ecosystem là phần chính của dự án này.**

👉 **[`.claude/skills/`](.claude/skills/)** — 12 dev skills + folder reserved cho QA/BA team
👉 **[`.claude/skills/README.md`](.claude/skills/README.md)** — navigation đầy đủ, catalog, story behind ecosystem

```
.claude/skills/
├── README.md       # Đọc trước — overview + 12 skill catalog
├── DEV/            # 12 skill em build (Dev role)
├── QA/             # Reserved cho team QA (sẽ đẩy vào)
└── BA/             # Reserved cho team BA (sẽ đẩy vào)
```

Highlight skills đáng xem nhất cho BGK:
1. [`dev-baokim-db-schema-designer`](.claude/skills/DEV/dev-baokim-db-schema-designer/SKILL.md) — story sinh từ chính bug em catch (8 violations Critical DB)
2. [`cross-llm-extraction-prompt`](.claude/skills/DEV/cross-llm-extraction-prompt/SKILL.md) — foundation 7 anti-hallucination patterns
3. [`tech-decision-framework`](.claude/skills/DEV/tech-decision-framework/SKILL.md) — workflow 7 bước decision + sinh ADR cross-domain
4. [`ocr-confidence-aggregator`](.claude/skills/DEV/ocr-confidence-aggregator/SKILL.md) — multi-signal scoring pattern (KHÔNG blend), reusable cho fraud/ranking

---

## Dự án OCR ngắn gọn

**Bài toán**: Nhân viên KSNB Baokim phải xử lý nhiều loại tài liệu (CCCD, hộ chiếu, GPKD, hợp đồng, hóa đơn, văn bản nước ngoài) từ merchant — nhập tay chậm + dễ sai + tài liệu tiếng Trung không đọc được.

**Giải pháp**: 1 API duy nhất nhận file → trả structured JSON + bản dịch + confidence.

**Kiến trúc — Harness 7 stages**:

```
Upload → Stage 0 (validate) → queue
       → Stage 1 (classifier — LLM Haiku)
       → Stage 2 (vision extractor — LLM Sonnet)
       → Stage 3 (rule regex + LLM judge KHÔNG xem ảnh)
       → Stage 4 (PII mask)
       → Stage 5 (confidence aggregator)
       → Stage 6 (persist + audit log partition by month)
```

| Stage | Skill mapping | Mục đích |
|---|---|---|
| 1 | `ocr-document-classifier` | Phân loại 15 doc type + ngôn ngữ |
| 2 | `dev-vision-prompt-designer` | Vision OCR → key-values + translation + confidence |
| 3 | `ocr-extraction-validator` | Rule regex + LLM judge (chống bias dương) |
| 4 | `vn-pii-masker` | Mask CCCD/phone/email cho audit (giữ raw cho KSNB) |
| 5 | `ocr-confidence-aggregator` | Weighted avg → quality bucket + requires_review |

→ Chi tiết: xem [`.claude/skills/`](.claude/skills/).

---

## Tech Stack

- **PHP 8.3 + Laravel 13**
- **MySQL 8** (utf8mb4_unicode_ci, partition by month cho audit logs)
- **LLM**: Gemini 2.5 Pro (provider-agnostic qua interface `LlmClient`, swap Claude qua env)
- **Storage**: local hoặc S3 (env switch)
- **Queue**: database driver (Redis-ready)
- **API doc**: Swagger UI (`/api/v1/docs`)

---

## API Endpoints

| Method | Path | Mục đích |
|---|---|---|
| `POST` | `/api/v1/ocr/extract` | Upload file → trả full response (sync mode) |
| `GET` | `/api/v1/ocr/history/{request_id}` | Lấy kết quả theo request_id (UUID v4) |
| `GET` | `/api/v1/ocr/history` | List paginated history |
| `GET` | `/api/v1/docs` | Swagger UI |

Auth: header `X-API-Key`. Error schema chuẩn R8 (error_code + message_vi/en + request_id).

---

## Setup local

Tiền điều kiện: PHP 8.3+, Composer 2, MySQL 8, Gemini API key.

```bash
composer install
cp .env.example .env
php artisan key:generate

# fill .env:
#   DB_* (MySQL connection)
#   OCR_API_KEY=<32-hex random>
#   OCR_LLM_PROVIDER=gemini
#   GEMINI_API_KEY=<paste key>

php artisan migrate
php artisan l5-swagger:generate
php artisan serve

# Worker queue (terminal khác)
php artisan queue:work --queue=default
```

> ⚠️ PHP CLI default `upload_max_filesize=2M`. Chạy server với flag:
> ```bash
> php -d upload_max_filesize=30M -d post_max_size=30M artisan serve
> ```

---

## Demo flow

1. Mở Swagger UI: `http://127.0.0.1:8000/api/v1/docs`
2. Authorize → paste `OCR_API_KEY` từ `.env`
3. POST `/api/v1/ocr/extract` → upload file CCCD/passport/hợp đồng
4. Xem response: `result.key_value_pairs`, `result.overall_confidence`, `result.requires_review`, `result.warnings`

Test fixtures: CCCD ảnh JPG, GPKD VN, English contract PDF, Chinese ID. Postman collection: [`docs/postman-collection.json`](docs/postman-collection.json).

---

## Test suite

```bash
vendor/bin/phpunit   # 36 tests, 109 assertions
```

Integration test mock `LlmClient` để chạy full pipeline 7 stages không cần Internet.

---

## Conventions

Tuân theo 8 rule [.claude/references/baokim-db-standard.md](.claude/references/baokim-db-standard.md):
- **BKM01** — KHÔNG foreign key (validate referential ở Service)
- **BKM02** — Eloquent only, cấm `DB::table()` standalone
- **BKM03** — Repository / Service / Controller 3-layer
- **BKM04** — KHÔNG query trong loop
- **BKM06** — Log tables partition by month
- **BKM08** — PII columns flag + audit

→ Xem [`.claude/references/baokim-db-standard.md`](.claude/references/baokim-db-standard.md) cho full SCR/IDR/SQR rules.

---

## Bài học kinh nghiệm (cho slide demo)

3 điểm em muốn nhấn mạnh:

1. **Skills shape design** — 12 skills encode tacit knowledge: anti-hallucination patterns, BKM standards, multi-signal confidence. AI không "tự nghĩ" tự do mà có khung sườn nhất quán. 11/12 skills (92%) reusable ngoài dự án này.

2. **Skill ecosystem dynamic, không static** — `dev-baokim-db-schema-designer` (skill 11) ra đời khi em catch 8 violations DB. `tech-decision-framework` (skill 12) ra đời khi em thấy thiếu skill phân tích trade-off. Pattern: **gap → catch → iterate skill → AI lần sau làm chuẩn hơn**.

3. **Defense-in-depth chống AI hallucination** — 6 lớp: prompt-level (7 AH patterns) + rule regex (Stage 3a) + independent LLM judge KHÔNG xem ảnh (Stage 3b) + cross-field logic (Stage 3c) + confidence cap (Stage 5) + disagreement detection. AI không "nói chuyện với AI không có giám sát".

---

## Security

- API keys cách ly trong `.env` (đã `.gitignore`)
- `X-API-Key` middleware constant-time compare (`hash_equals`)
- File upload sandboxed: max 10MB, MIME magic-bytes check, sha256 dedupe
- PII masking trong audit logs (Stage 4 chạy luôn — `*_masked` columns lưu song song với raw)
- KSNB internal user — RAW available cho copy-paste workflow

---

## Owner

**Duy** ([duy@baokim.vn](mailto:duy@baokim.vn)) — Senior PHP dev, Baokim
