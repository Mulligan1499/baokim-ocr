# Changelog — dev-baokim-api-design

## v0.1.0 — 2026-05-21

Initial draft. Encode tacit knowledge từ AC v1 work của dự án OCR KSNB:

- 8 quyết định kiến trúc (versioning, error schema R8, auth, idempotency, pagination, envelope, OpenAPI, backward compat)
- 3 case study trong references/examples.md: OCR v1 (real), Merchant Onboarding (hypothetical reusability), v1→v2 bump audit
- 8 template code skeleton: route file, ApiErrorResponse, FormRequest, V1 Resource, idempotency repo methods, AuthenticateApiKey middleware, SwaggerInfo, pagination endpoint
- Anti-example: V0 OCR routes trước refactor — slide demo có thể dùng để show diff

## Roadmap

- v0.2.0: thêm rate limiting decision tree (Redis bucket vs sliding window), thêm versioning strategy cho file upload size limits
- v0.3.0: split references/templates.md thành references/templates/ folder nếu > 300 lines
- v1.0.0: graduate sang `active` lifecycle sau khi dùng cho dự án thứ 2 (Merchant Onboarding API hoặc Internal Tooling API) không sửa
