# Changelog: ocr-extraction-validator

Stage 3 Validator skill (rule-based + LLM-as-judge) cho harness 7 stages dự án OCR Baokim.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- Workflow 4 steps: identify rules → 3a programmatic → 3b LLM judge → aggregate
- 8 field validation rules table: CCCD, Passport VN, MST, Phone VN, Email, Date, Amount, Account number
- Rule-based validator PHP service skeleton `RuleBasedValidator` (no LLM call)
- LLM-as-judge System Prompt template với explicit anti-bias design
- PHP service skeleton `Stage3ValidatorService` với 2 sub-stage tách bạch
- 4 examples: CCCD happy, CCCD invalid format, anti-bias dương (amount cross-check), CV reusability

### Core design principles

- Stage 3 phải INDEPENDENT với Stage 2 (separate model call, separate prompt, separate context)
- Empty value (`value=""`) KHÔNG fail rule — đó là correct anti-hallucination từ Stage 2
- Stage 3 CHỈ flag, KHÔNG auto-correct (correction là Stage 5 hoặc human review)
- 3a free + cheap, 3b accurate semantic check — cả hai cần thiết

### Inherit từ

- `cross-llm-extraction-prompt`: 7 patterns AH
- `dev-baokim-laravel-repo-pattern`: PHP service convention BKM03

### Known limitations (v0.1.0)

- Validation rules hardcode trong RuleBasedValidator (tách v0.2.0)
- 3b LLM judge dùng Sonnet 4.6 (chưa benchmark Haiku có đủ accuracy không)
- Chưa có cross-field validation (vd: dob + age consistency)
- Test prompts 8 cases, target 12+ trước active

### Roadmap

- v0.2.0: tách `references/validation-rules/` per field type
- v0.3.0: thêm validators industry specific (bằng lái xe, BHXH, đăng ký xe)
- v0.4.0: cross-field validation rules
- v1.0.0 (active): 500+ extractions tested, ROC AUC ≥ 0.85
