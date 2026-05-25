# Skill Ecosystem — Baokim OCR Project

**Cuộc thi Claude Skill 2026 — Baokim**

> Repo skills xây dựng cho dự án **API OCR phục vụ team KSNB (Kiểm soát nội bộ) Baokim**. Skills tổ chức theo 3 vai trò: **Dev** (em xây 12 skill), **QA** (team Tester đẩy vào), **BA** (team BA đẩy vào).

→ Project README: [../../README.md](../../README.md) cho chi tiết kiến trúc + cách chạy API.

---

## Dự án OCR ngắn gọn

API duy nhất `POST /api/v1/ocr/extract` nhận file (ảnh/PDF tiếng Việt/Anh/Trung) → trả structured JSON gồm:
- Toàn bộ text trong tài liệu
- Key-value pairs (CCCD, MST, ngày tháng, số tiền...)
- Bản dịch tiếng Việt (nếu tài liệu ngoại ngữ)
- Confidence score per field
- Cờ `requires_review` báo KSNB phải rà soát thủ công

**Kiến trúc**: Harness 7 stages — 3 LLM call (Classifier → Vision Extractor → Judge) + 4 deterministic stage (Validation, Rule check, PII Mask, Confidence Aggregator). Defense-in-depth chống AI hallucination.

**Tech stack**: PHP 8.3 + Laravel 13, MySQL 8, Gemini Vision API (swap được Claude qua interface `LlmClient`).

---

## 📁 Folder Structure

```
.claude/skills/
├── README.md                 # File này
├── PROGRESSIVE_DISCLOSURE.md # Pattern lazy-load detail
├── DEV/                      # 12 skill em build  
├── QA/                       # Reserved cho team QA
└── BA/                       # Reserved cho team BA
```

| Folder | Số skill | Owner | Mô tả |
|---|---|---|---|
| [`DEV/`](DEV/) | **12** | duy@baokim.vn | Skills cho Dev role — code Laravel/SQL/API + OCR pipeline + cross-domain foundations |
| [`QA/`](QA/) | 0 (pending) | Team QA | Skills cho Quality Assurance — test case design, bug report, regression |
| [`BA/`](BA/) | 0 (pending) | Team BA | Skills cho Business Analyst — user story, AC writing, requirement extraction |

→ Mỗi folder có `README.md` riêng với catalog + hướng dẫn.

---

## 12 Dev Skills (chia 4 reusability tier)

| # | Skill | Tier | Phase | 1-line description |
|---|---|---|---|---|
| 01 | [`cross-llm-extraction-prompt`](DEV/cross-llm-extraction-prompt/SKILL.md) | 🟢 Cross-domain | Build (foundation) | Foundation prompt LLM extraction, 7 anti-hallucination patterns |
| 02 | [`tech-decision-framework`](DEV/tech-decision-framework/SKILL.md) | 🟢 Cross-domain | Build | Phân tích trade-off + sinh ADR (Architecture Decision Record) |
| 03 | [`vn-pii-masker`](DEV/vn-pii-masker/SKILL.md) | 🟢 Cross-domain | Runtime (Stage 4) | Che PII Việt Nam (CCCD/MST/phone/email) giữ 4 ký tự cuối |
| 04 | [`feedback-to-skill-refiner`](DEV/feedback-to-skill-refiner/SKILL.md) | 🟢 Cross-domain | Post-deploy weekly | Phân tích thao tác KSNB → đề xuất sửa skill khác |
| 05 | [`dev-baokim-api-design`](DEV/dev-baokim-api-design/SKILL.md) | 🔵 Baokim platform | Build | REST API theo 8 chuẩn Baokim (versioning/error R8/idempotency) |
| 06 | [`dev-baokim-db-schema-designer`](DEV/dev-baokim-db-schema-designer/SKILL.md) | 🔵 Baokim platform | Build | Sinh migration theo BKM/SCR/IDR, tránh 8 trap Laravel default |
| 07 | [`dev-baokim-laravel-repo-pattern`](DEV/dev-baokim-laravel-repo-pattern/SKILL.md) | 🔵 Baokim platform | Build | Repository/Service/Controller 3-layer (BKM03) |
| 08 | [`dev-baokim-sql-reviewer`](DEV/dev-baokim-sql-reviewer/SKILL.md) | 🔵 Baokim platform | Build | Review SQL/Eloquent theo SQR/IDR/BKM rules |
| 09 | [`dev-vision-prompt-designer`](DEV/dev-vision-prompt-designer/SKILL.md) | 🟡 Pattern reusable | Build (Stage 2) | Prompt Claude Vision API với 7 AH patterns |
| 10 | [`ocr-extraction-validator`](DEV/ocr-extraction-validator/SKILL.md) | 🟡 Pattern reusable | Runtime (Stage 3) | Validate output Stage 2 độc lập (rule + LLM judge KHÔNG xem ảnh) |
| 11 | [`ocr-confidence-aggregator`](DEV/ocr-confidence-aggregator/SKILL.md) | 🟡 Pattern reusable | Runtime (Stage 5) | Multi-signal scoring → quality bucket + requires_review flag |
| 12 | [`ocr-document-classifier`](DEV/ocr-document-classifier/SKILL.md) | 🟠 Project-specific | Runtime (Stage 1) | Phân loại 15 doc type KSNB + ngôn ngữ + extraction strategy |

**Tier legend**:
- 🟢 **Cross-domain**: bất kỳ team/dự án/công ty dùng được
- 🔵 **Baokim platform**: mọi dự án Laravel/API tại Baokim
- 🟡 **Pattern reusable**: design generic, OCR chỉ là 1 ví dụ
- 🟠 **Project-specific**: chỉ cho OCR Baokim KSNB

→ **11/12 skill (92%) reusable ngoài dự án này**.

---

## Standards Compliance

Skills theo **[Anthropic Claude Skill spec](https://code.claude.com/docs/en/skills)** (Agent Skills open standard) + **Baokim enterprise extensions**:

```yaml
---
# Anthropic core
name: <skill-name>
description: <1 câu cô đọng, ≤300 chars>
when_to_use: |
  Triggers: <list>
  Anti-triggers: <list>

# Baokim extensions
owner, version, lifecycle, domain, created, updated, tags
---
```

Reference: [`../references/baokim-db-standard.md`](../references/baokim-db-standard.md) — Baokim DB rules (BKM01-08, SCR, IDR, SQR).

---

## Story behind the ecosystem

Skill ecosystem **dynamic, không static**. Trong 3 tuần build, em catch nhiều gap qua review production:

- Initial: 10 skills
- **Skill 11** (`dev-baokim-db-schema-designer`) sinh ra khi em audit DB → catch 8 violations Critical do AI dùng Laravel default `$table->timestamps()` vi phạm SCR02.4 → encode 8 trap vào skill mới
- **Skill 12** (`tech-decision-framework`) sinh ra khi em thấy thiếu skill phân tích trade-off cross-domain
- **Skill `feedback-to-skill-refiner`** đóng vòng lặp — hằng tuần đọc log thao tác KSNB → đề xuất sửa skill khác

→ Pattern: **gap → catch → iterate skill → AI lần sau làm chuẩn hơn**. 12 skill này là snapshot tại thời điểm submission, không phải con số cuối.

---

## Cho BGK navigate nhanh

1. **Bắt đầu từ [`DEV/README.md`](DEV/README.md)** — catalog đầy đủ 12 skill với link
2. **Đọc 1 skill mẫu**: [`DEV/dev-baokim-db-schema-designer/SKILL.md`](DEV/dev-baokim-db-schema-designer/SKILL.md) — skill em đáng tự hào nhất (story behind: bịt gap-time)
3. **Xem foundation**: [`DEV/cross-llm-extraction-prompt/SKILL.md`](DEV/cross-llm-extraction-prompt/SKILL.md) — 7 anti-hallucination patterns mà 5 skill khác inherit
4. **Xem skill cross-domain**: [`DEV/tech-decision-framework/SKILL.md`](DEV/tech-decision-framework/SKILL.md) — workflow 7 bước decision + output ADR

---

## Lưu ý cấu trúc

Folder nested `DEV/QA/BA/` thay vì flat theo Anthropic spec — để clean cho git review + tách rõ ownership theo vai trò. Trade-off: Claude Code auto-load chỉ work với flat path. Khi dev local cần activate, copy skill ra `.claude/skills/<name>/` tạm thời.

→ Cho mục đích submission (BGK review markdown), nested OK.

---

*Owner: Duy ([duy@baokim.vn](mailto:duy@baokim.vn)) | Last updated: 2026-05-25*
