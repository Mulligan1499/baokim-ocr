# update-tc-results-from-log

Skill cập nhật kết quả test từ Robot Framework run vào 2 file Excel.

## Tại sao cần skill này

Sau mỗi lần chạy `robot`, bạn nhận được:
- `log.html` — chi tiết từng TC step
- `report.html` — báo cáo Pass/Fail
- `output.xml` — raw data (cùng folder)

QA team Baokim quản lý kết quả thủ công trong:
- `testcase_ocr.xlsx` — danh sách 140 TC, mỗi TC có cột Result/Bug ID/Tester/Test Date/Note cho 3 Round
- `logbug.xlsx` — bug log với 21 cột (STT, Task, Severity, Status formula, ...)

Skill này tự chuyển kết quả Robot → 2 file Excel theo đúng format, **không phải copy-paste tay**.

## Cách dùng

### Auto (recommended)

Wrapper `OCR/scripts/run_tests.sh` đã wire sẵn — chạy như thường:

```bash
./OCR/scripts/run_tests.sh OCR/tests/ocr/api1_extract/01_functional.robot
```

Sau khi Robot xong, script tự update xlsx. Mở:
```bash
open OCR/resources/test_cases/testcase_ocr.xlsx
open OCR/resources/test_cases/logbug.xlsx
```

### Manual

Khi muốn override (vd ghi vào Round 2):

```bash
.venv/bin/python OCR/scripts/update_results.py \
  OCR/result/latest/output.xml \
  --round 2 \
  --tester "Tho Le"
```

### Qua Claude (gọi skill)

Trong Claude Code chat:
```
/update-tc-results-from-log
```

Hoặc nói tự nhiên: "update kết quả run vừa rồi vào xlsx round 2 với tester là Tho Le". Skill sẽ tự detect và chạy script.

## File được update

| File | Path | Sheet |
|---|---|---|
| Test case | `OCR/resources/test_cases/testcase_ocr.xlsx` | `ocr testcase api` |
| Bug log | `OCR/resources/test_cases/logbug.xlsx` | `Logbug` |

Chi tiết column mapping xem `SKILL.md`.

## Customize

Logic nằm trong `OCR/scripts/update_results.py`. Sửa hằng số:
- `LOGBUG_TASK = 'OCR-CONTEST'`
- `LOGBUG_FUNCTION = 'OCR API - POST /api/v1/ocr/extract'`
- `severity_from_message()` — rule phân loại severity
