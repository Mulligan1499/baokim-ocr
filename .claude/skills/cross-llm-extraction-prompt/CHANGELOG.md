# Changelog: cross-llm-extraction-prompt

Foundation skill cho mọi LLM extraction task. Cross-domain reusable.

## [0.1.0] - 2026-05-15

### Added

- Initial draft của foundation skill
- 7 anti-hallucination patterns (generic, không gắn vision/text):
  - AH-1: Empty value instead of guess
  - AH-2: Per-field confidence calibration
  - AH-3: Refusal pattern for out-of-scope input
  - AH-4: Reasoning trace before extraction
  - AH-5: Critical field validation flag
  - AH-6: Translation grounding
  - AH-7: Negative few-shot example (single point of failure)
- Workflow 4 steps: identify contract → apply patterns → generate 4 components → validate 4 test cases
- Generic templates: System Prompt, User Prompt, JSON Output Schema
- 3 examples cross-domain:
  - OCR vision (foundation cho dev-vision-prompt-designer)
  - CV parsing (HR domain — demo reusability)
  - Customer feedback structured (Sales/CS — demo reusability)
- Specialization map liệt kê 4 specialized skills inherit từ đây

### Why this skill exists

- Tránh duplicate 7 patterns AH trong mỗi specialized skill
- Demo Sếp câu 4 ("team khác dùng lại được không?") — skill này CHÍNH LÀ proof of reusability
- Foundation cho refactor `dev-vision-prompt-designer` v0.2.0 (sẽ reference skill này thay vì duplicate)

### Known limitations (v0.1.0)

- 4 specialized skills trong map mới có 1 đã build (`dev-vision-prompt-designer` v0.1.0)
- Chưa có example cho mixed-input (text + image trong cùng task)
- Test prompts mới có 8 cases, target 12+ trước active lifecycle

### Roadmap

- v0.2.0: thêm references/patterns-detail/ tách 7 patterns ra file riêng với rationale + research citations
- v0.3.0: thêm specialized skills `ba-cv-parser`, `legal-clause-extractor`, `cross-feedback-analyzer`
- v1.0.0 (active): sau 30+ runs across 3+ domains
