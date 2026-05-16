# Changelog: vn-pii-masker

Cross-domain skill mask PII Vietnamese trong logs/audit/exports.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- 10 PII categories với regex detection + mask patterns:
  - CCCD 12 số, CMND 9 số, Passport VN/foreign
  - MST doanh nghiệp (10/13 số)
  - Phone VN (định dạng 03/05/07/08/09)
  - Email, full name VN, account number, IP, date of birth
- Workflow 4 steps: detect → apply mask → audit trail → code skeleton
- PHP code skeleton Laravel service `VnPiiMasker`
- 4 examples cross-domain:
  - KSNB audit log (project OCR)
  - HR CV processing
  - Sales lead CSV export
  - Sentry/Datadog error message
- Implementation notes: performance, edge cases, Laravel integration
- Compliance reference: PDPL VN, Nghị định 13/2023, TT 39/2016, GDPR Article 32

### Why this skill exists

- Stage 4 trong harness 7 stages của project OCR (file 02 Section 4)
- Cross-domain reusable demo cho Sếp câu 4 — HR/Pháp chế/Sales/Ops đều có thể dùng ngay
- Compliance baseline cho mọi service Baokim handle PII (không chỉ OCR)

### Known limitations (v0.1.0)

- Context-aware detection cho 9-số (CMND vs random number) còn heuristic, có false positive
- Tên VN: chỉ handle format 3-4 từ chuẩn, miss tên 2 từ hoặc 5+ từ
- Chưa có specialized patterns: bằng lái xe, BHXH, MST cá nhân
- Performance chưa benchmark, chưa cache compiled regex
- Test prompts mới 8 cases, target 15+ trước active

### Roadmap

- v0.2.0: tách references/pii-patterns.md với regex chi tiết
- v0.3.0: thêm specialized patterns industry
- v0.4.0: Laravel middleware auto-mask cho audit_logs
- v1.0.0 (active): production-tested 1000+ log lines/day
