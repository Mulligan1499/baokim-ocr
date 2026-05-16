# Baokim OCR API — Project Context

Dự án cuộc thi Claude Skill: API OCR cho team KSNB onboarding merchant.
3 tuần, deadline cuối tháng 5/2026. Dev senior PHP (Duy).

## Stack
- PHP 8.2 + Laravel 11, MySQL 8 (utf8mb4_unicode_ci), Redis, S3-compatible storage
- Anthropic Claude Sonnet 4.6 Vision (Stage 2), Haiku 4.5 (Stage 1, 3b)

## Architecture: Harness 7 stages
- Stage 0: Permission Gate (Laravel validation)
- Stage 1: Document Classifier → skill ocr-document-classifier
- Stage 2: Vision Extractor → skill dev-vision-prompt-designer
- Stage 3: Validator (rule + LLM judge) → skill ocr-extraction-validator
- Stage 4: PII Masker → skill vn-pii-masker
- Stage 5: Confidence Aggregator → skill ocr-confidence-aggregator
- Stage 6: Persist + Audit (3 bảng MySQL)

## Conventions (BẮT BUỘC, xem .claude/references/baokim-db-standard.md)
- BKM01: KHÔNG dùng FOREIGN KEY
- BKM02: Eloquent only, cấm DB::table standalone
- BKM03: Repository/Service/Controller 3-layer
- BKM04: KHÔNG query trong loop
- BKM06: Log tables PHẢI partition
- BKM08: PII columns flag for encryption + audit

## Skills available (.claude/skills/)
9 skills auto-load khi trigger phrases match. Run `claude /skills` để list.

## Git commit convention (track AI vs human work cho demo Sếp)
- `[ai-major] ...` : AI sinh > 70% code, human review pass
- `[ai-assist] ...` : AI sinh < 70%, human edit nhiều
- `[human] ...` : Human viết, không dùng AI

## Communication
Tiếng Việt là chính. Code + naming tiếng Anh.
