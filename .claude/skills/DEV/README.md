# DEV Skills (12 skills)

> Folder chứa 12 skill em build cho vai trò **Dev** trong cuộc thi Claude Skill Baokim.

## Catalog

| # | Skill | Tier | Phase | Mô tả ngắn |
|---|---|---|---|---|
| 01 | [`cross-llm-extraction-prompt`](cross-llm-extraction-prompt/SKILL.md) | 🟢 A | Build (foundation) | Foundation prompt LLM extraction, 7 anti-hallucination patterns |
| 02 | [`tech-decision-framework`](tech-decision-framework/SKILL.md) | 🟢 A | Build | Phân tích trade-off + sinh ADR |
| 03 | [`vn-pii-masker`](vn-pii-masker/SKILL.md) | 🟢 A | Runtime (Stage 4) | Che PII Việt Nam (CCCD/MST/phone/email) |
| 04 | [`feedback-to-skill-refiner`](feedback-to-skill-refiner/SKILL.md) | 🟢 A | Post-deploy weekly | Phân tích thao tác user → đề xuất sửa skill |
| 05 | [`dev-baokim-api-design`](dev-baokim-api-design/SKILL.md) | 🔵 B | Build | REST API theo 8 chuẩn Baokim |
| 06 | [`dev-baokim-db-schema-designer`](dev-baokim-db-schema-designer/SKILL.md) | 🔵 B | Build | Sinh migration theo BKM/SCR/IDR |
| 07 | [`dev-baokim-laravel-repo-pattern`](dev-baokim-laravel-repo-pattern/SKILL.md) | 🔵 B | Build | Repository/Service/Controller 3-layer (BKM03) |
| 08 | [`dev-baokim-sql-reviewer`](dev-baokim-sql-reviewer/SKILL.md) | 🔵 B | Build | Review SQL/Eloquent theo SQR/IDR/BKM |
| 09 | [`dev-vision-prompt-designer`](dev-vision-prompt-designer/SKILL.md) | 🟡 C | Build (Stage 2 prompt) | Prompt Claude Vision với 7 AH patterns |
| 10 | [`ocr-extraction-validator`](ocr-extraction-validator/SKILL.md) | 🟡 C | Runtime (Stage 3) | Validate output Stage 2 độc lập |
| 11 | [`ocr-confidence-aggregator`](ocr-confidence-aggregator/SKILL.md) | 🟡 C | Runtime (Stage 5) | Multi-signal scoring → quality bucket |
| 12 | [`ocr-document-classifier`](ocr-document-classifier/SKILL.md) | 🟠 D | Runtime (Stage 1) | Phân loại 15 doc type KSNB |

**Tier legend**:
- 🟢 A: Cross-domain (mọi team, mọi industry)
- 🔵 B: Baokim platform (mọi dự án Baokim)
- 🟡 C: Pattern reusable (OCR là 1 ví dụ)
- 🟠 D: Project-specific (chỉ OCR Baokim KSNB)

Xem [`../README.md`](../README.md) cho overview tổng + extension guide.

## Owner

[duy@baokim.vn](mailto:duy@baokim.vn)
