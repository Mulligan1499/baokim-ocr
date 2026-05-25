# api-autotest-from-spec

Skill build Robot Framework autotest **spec-driven** cho bất kỳ REST API nào.

## Bao gồm

```
api-autotest-from-spec/
├── SKILL.md                              # Quy trình 7 bước
├── README.md                             # File này
├── templates/
│   ├── structure.md                      # Layout chuẩn
│   ├── assertion_helpers_template.robot  # Schema + format + business-rule helpers
│   ├── formula_validator.robot           # 4 ví dụ formula pattern
│   └── csv_datadriven.robot              # Pattern DataDriver
└── references/
    ├── spec_audit_checklist.md           # 10-section checklist khi đọc spec
    └── csv_data_format.md                # Quy tắc CSV cho DataDriver
```

## Kích hoạt

Skill tự kích hoạt khi user nói:
- "build autotest cho API X từ tài liệu"
- "verify response có đủ field spec không"
- "kiểm tra dev áp dụng công thức đúng chưa"
- "convert testcase manual sang auto"

## Khác gì so với `scaffold-api-test`?

| | scaffold-api-test | api-autotest-from-spec |
|---|---|---|
| Input | Endpoint URL + framework | Spec PDF/OpenAPI + manual TC xlsx/csv |
| Output | Skeleton TC file | Schema validators + business-rule validators + full TC suite |
| Validation depth | Basic status code | Full schema (top + nested + item) + formula |
| Tái sử dụng | Per endpoint | Per API project — schema/formula helper dùng được cho mọi endpoint cùng project |

Có thể dùng kết hợp: dùng `scaffold-api-test` cho TC đơn lẻ, dùng `api-autotest-from-spec` khi build bộ test hoàn chỉnh có schema validation.

## Ví dụ thực tế

Project `Autotesting_ocr` áp dụng skill này:
- Spec: `Tai_lieu_API_OCR_Dinh_danh_giay_to.pdf`
- Manual TC: `testcase_ocr.xlsx` (133 TC)
- Output: 11 file Robot suite + 3 CSV data-driven + assertion helpers với 3 lớp validator
- Verify dev áp dụng đúng formula R3 weighted average via `Verify Overall Confidence R3`
