# Changelog: dev-vision-prompt-designer

Skill thiết kế prompt Claude Vision cho Stage 2 Vision Extractor.

## [0.1.0] - 2026-05-15

### Added

- Initial draft của skill (priority cao nhất tuần 1 — Section 11 file 02-tech-decisions)
- 7 anti-hallucination patterns (AH-1 đến AH-7):
  - AH-1: Empty value instead of guess
  - AH-2: Per-field confidence calibration scale
  - AH-3: Refusal pattern cho non-document
  - AH-4: Reasoning trace trước extraction
  - AH-5: Critical field validation flag (không auto-correct)
  - AH-6: Translation grounding (không bịa khi dịch)
  - AH-7: Negative few-shot example (single point of failure)
- Workflow 4 steps: identify yêu cầu → apply 7 patterns → generate 4 components → validate 4 test cases
- Templates ready-to-use: System Prompt, User Prompt, JSON Output Schema
- 4 examples cover:
  - CCCD VN happy path
  - Vận đơn tiếng Trung (translation rules cho text vs numbers)
  - Anti-hallucination kick-in case (ảnh mờ)
  - Reusability — CV cho HR domain
- Critical fields defaults cho 6 doc types: id_card, passport, invoice, contract, bill_of_lading, customs_declaration
- "What NOT to do" section với 7 anti-patterns cụ thể
- Cost-aware notes (prompt caching, image resolution, output length)

### Known limitations (v0.1.0)

- Taxonomy 15 doc types inline trong SKILL.md, chưa tách `references/` — sẽ refactor tuần 3 (Option A roadmap đã chốt)
- 7/15 doc types có critical_fields defaults, 6 còn lại (contract_vi, contract_foreign, bank_statement, legal_document, company_charter, power_of_attorney, employment_contract) cần dev truyền explicit
- Chưa có bbox grounding pattern (chờ Anthropic Vision API release native bbox support)
- Test prompts mới có 6 trigger + 5 anti-trigger cases, target 10+ trước chuyển lifecycle `active`
- Chưa có example cho mixed-language documents (vd: hợp đồng VN có phần phụ lục tiếng Anh)

### Migration notes

- Skill này coupling với Stage 2 trong harness 7 stages (file 02-tech-decisions Section 4)
- Khi Stage 1 Classifier (skill `vn-document-classifier`) đổi taxonomy → cần sync
- Khi Stage 3 Validator (skill `extraction-validator`) đổi validation rules → cần sync critical fields list

### Roadmap

- v0.2.0 (target: D11 tuần 3, theo Option A refactor): tách references/, thêm taxonomies/hr.md cho reusability demo
- v0.3.0 (post-cuộc-thi): tích hợp bbox grounding nếu Anthropic release
- v1.0.0 (lifecycle: active): sau 50+ runs production + golden set 100 cases
