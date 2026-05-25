# robot-test-data-gen

Skill chuyển Excel manual test case → CSV test data cho Robot Framework auto test theo pattern single-field validation.

## Cài đặt skill vào Claude

1. Giải nén file zip
2. Upload toàn bộ folder `robot-test-data-gen/` vào Claude Skills (qua UI hoặc API)
3. Khi anh chat với Claude, dùng các trigger phrase:
   - "gen CSV từ test case"
   - "convert manual TC sang data-driven"
   - "tạo file data cho auto test"

## Cấu trúc

```
robot-test-data-gen/
├── SKILL.md                              # Entry point — Claude đọc đầu tiên
├── references/
│   ├── column-mapping-guide.md           # Heuristic map Excel header → CSV column
│   ├── sentinel-value-guide.md           # Detect <EMPTY>/<NULL>/<MISSING>
│   └── e2e-detection-guide.md            # Detect E2E flow TC để skip
├── scripts/
│   └── convert_excel_to_csv.py           # Reference implementation Python
└── examples/
    ├── _generate_examples.py             # Script gen example input
    ├── login_manual_tc.xlsx              # Input mẫu (header tiếng Anh)
    ├── login_data.csv                    # Output CSV mong đợi
    ├── login_warnings.md                 # E2E TC bị skip
    ├── create_order_manual_tc.xlsx       # Input mẫu (header tiếng Việt)
    ├── create_order_data.csv             # Output CSV mong đợi
    └── create_order_warnings.md          # E2E TC bị skip
```

## Pattern xử lý

| Loại Manual TC | Output |
|---|---|
| Happy path (Description = "hợp lệ"/"thành công") | 1 CSV row, field_name + test_value rỗng |
| Single-field validation | 1 CSV row với field_name + sentinel hoặc literal value |
| Multi-field ("thiếu A và B") | Tách thành N CSV row, Case_id suffix a/b/c |
| E2E flow (nhiều endpoint kết hợp) | SKIP, ghi vào `<feature>_warnings.md` |

## Quick test reference script

```bash
pip install openpyxl
python3 scripts/convert_excel_to_csv.py examples/login_manual_tc.xlsx login --out-dir=examples/
```

Script là reference — Claude tự adapt khi anh dùng skill. Không cần chạy tay nếu đã dùng skill qua Claude.

## Liên quan

- Skill `qa-tester` — gen Excel manual TC từ requirement/spec
- Skill `robot-framework-automation` (existing) — setup project Robot Framework
- Skill `robot-script-writer` (TBD) — gen .robot script từ CSV này
