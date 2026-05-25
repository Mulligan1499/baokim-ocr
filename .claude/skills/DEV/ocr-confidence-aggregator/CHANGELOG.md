# Changelog: ocr-confidence-aggregator

Stage 5 Confidence Aggregator skill cho harness 7 stages dự án OCR Baokim.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- Workflow 4 steps: identify signals → apply formula → bucket → output
- Weighted formula: self×0.3 + rule×0.4 + judge×0.3
- Forced cap rule: critical field + rule fail → final ≤ 0.4
- 3-bucket system: high (≥0.85), medium (0.5-0.85), low (<0.5)
- 4 warnings: LOW_CONFIDENCE, MEDIUM_CONFIDENCE, CRITICAL_FIELD_FAILED, MULTI_SIGNAL_DISAGREE, LOW_IMAGE_QUALITY
- Full PHP service skeleton `Stage5ConfidenceAggregatorService` (production-ready code, không phải pseudocode)
- 4 examples:
  - High confidence happy path
  - Critical field rule fail (forced cap)
  - Signal disagreement
  - Reusability — fraud detection (cùng pattern, đổi signals)

### Core design principles

- Multi-signal hard-coded weighted formula — không phải ML calibration
- Forced cap cho critical field rule fail là safety mechanism
- Signal disagreement flag trigger human review
- Output structure cung cấp đủ debug info (signals raw + warnings + priority fields)

### Reference

- file 02 Section 10 (Confidence strategy)
- `ocr-extraction-validator`: provides Stage 3 input
- `dev-baokim-laravel-repo-pattern`: PHP convention

### Known limitations (v0.1.0)

- Weights + thresholds hardcode (constants) — v0.2.0 sẽ config qua `config/ocr.php`
- Chưa có per-doc-type weight adaptation
- Test coverage: 8 cases, target 15+ trước active
- Calibration chưa benchmark — Stage 2 self-report có bias hệ thống không?

### Roadmap

- v0.2.0: thresholds config, không hardcode
- v0.3.0: adaptive weights per doc_type
- v0.4.0: calibration framework (test against golden set, adjust weights)
- v1.0.0 (active): 1000+ extractions, calibration error < 5%
