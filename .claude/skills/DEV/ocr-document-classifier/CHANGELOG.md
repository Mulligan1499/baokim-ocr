# Changelog: ocr-document-classifier

Stage 1 Classifier skill cho harness 7 stages dự án OCR Baokim KSNB.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- Workflow 4 steps: classification contract → patterns AH → Haiku-optimized prompt → PHP service skeleton
- System Prompt + User Prompt templates Haiku-friendly (compact, < 1000 tokens)
- JSON Output Schema (flat, không nested deep — Haiku friendly)
- PHP service skeleton `Stage1ClassifierService` theo BKM03 + audit log BKM06
- 4 examples: CCCD happy path, mixed-language B/L, ambiguous contract, non-document refusal
- Reusability section: taxonomies cho HR / Pháp chế / Sales (pluggable)

### Inherit từ

- `cross-llm-extraction-prompt`: 7 anti-hallucination patterns
- `dev-baokim-laravel-repo-pattern`: PHP service skeleton convention

### Known limitations (v0.1.0)

- Taxonomy 15 KSNB hardcode trong prompt examples (tách references/taxonomies/ v0.2.0)
- Chưa benchmark accuracy Haiku 4.5 với 15 KSNB doc types
- Chưa có ensemble strategy cho ambiguous cases
- Test prompts 8 cases, target 15+ trước active

### Roadmap

- v0.2.0: tách references/taxonomies/{ksnb,hr,legal,sales}.md
- v0.3.0: ensemble (2 Haiku calls + vote) cho conf < 0.7
- v1.0.0 (active): 100+ runs, accuracy benchmark ≥ 90%
