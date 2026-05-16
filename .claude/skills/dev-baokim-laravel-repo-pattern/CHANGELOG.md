# Changelog: dev-baokim-laravel-repo-pattern

Foundation skill code Laravel theo Repository/Service/Controller pattern Baokim.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- Workflow 4 steps: identify scope → design 3 layers → apply BKM rules → output 4 deliverables
- Templates ready-to-use:
  - Repository skeleton (PackageRepository)
  - Service skeleton (PackageCreationService với transaction + validation)
  - Controller skeleton (thin)
  - Sample Feature test
- 3 examples:
  - OCR feature (current project) — Stage 1-7 service breakdown
  - Payment domain (generic Baokim)
  - Code review (existing controller với 5 violations)
- Pre-merge BKM compliance checklist
- "What NOT to do" 10 anti-patterns

### Why this skill exists

- D3-D5 tuần 1: code Laravel vertical slice cần ngay
- Skill này guide Claude Code khi generate code OCR backend
- Reusable cho mọi project Baokim sau này (payment, auth, merchant management...)

### Known limitations (v0.1.0)

- Templates dùng PackageRepository làm reference, chưa có per-domain templates
- Chưa integrate với phpstan/larastan để auto-detect violations
- Test prompts mới 8 cases, target 12+ trước active
- BKM07 (hash search) coverage chưa có example cụ thể

### Roadmap

- v0.2.0: tách references/code-templates/ với per-domain (payment, auth, ocr, audit)
- v0.3.0: phpstan custom rules detect BKM violations automatic
- v1.0.0 (active): production-tested 5+ features
