---
name: update-tc-results-from-log
description: |
  [WHAT] Cập nhật kết quả test từ Robot Framework `output.xml` (hoặc đọc cùng folder với log.html) vào:
    1) `OCR/resources/test_cases/testcase_ocr.xlsx` — cột Result/Bug ID/Tester/Test Date/Note theo round.
    2) `OCR/resources/test_cases/logbug.xlsx` — append entry mới cho mỗi TC FAIL theo format có sẵn.
  [WHEN] User nói: "update kết quả test vào testcase_ocr.xlsx", "ghi bug vào logbug", "cập nhật xlsx sau khi chạy robot", "log test result", "auto fill kết quả từ log.html", "tổng hợp kết quả test ra file Excel".
  [SKIP] Không dùng nếu chưa có Robot run nào (chưa có `OCR/result/<timestamp>/output.xml`). Không dùng cho project khác — column mapping hardcoded cho 2 file xlsx của Baokim OCR.
---

# update-tc-results-from-log

Skill này gọi script `OCR/scripts/update_results.py` đọc Robot output.xml gần nhất và ghi vào 2 file Excel theo format đã thoả thuận với QA team Baokim.

## Khi nào skill auto-chạy

Wrapper `OCR/scripts/run_tests.sh` đã wire sẵn — mỗi lần `./OCR/scripts/run_tests.sh ...` xong, script tự chạy. **Không cần invoke skill thủ công cho trường hợp này.**

User invoke skill này khi:
- Đã có `OCR/result/<timestamp>/output.xml` nhưng xlsx chưa được update (vd script lỗi, hoặc chạy robot bằng cách khác)
- Muốn ghi vào round khác (Round 2/3 thay vì Round 1)
- Muốn thay đổi tester name
- Muốn re-run xlsx update cho 1 output cũ

## Cách invoke

```bash
# Mặc định: output gần nhất, Round 1, tester=Auto-Robot, KHÔNG clean
.venv/bin/python OCR/scripts/update_results.py OCR/result/latest/output.xml

# Đầy đủ flags
.venv/bin/python OCR/scripts/update_results.py \
  <path_to_output.xml> \
  --round 2 \
  --tester "Tho Le" \
  --clean-round
```

Args:
- `output_xml` (required): path đến `output.xml` Robot. `log.html` không parse được — phải dùng `output.xml`.
- `--round {1,2,3}`: round nào trong xlsx (default 1).
- `--tester NAME`: ghi vào cột Tester (default "Auto-Robot").
- `--clean-round`: xoá các bug cũ trong logbug.xlsx có STT trùng Bug IDs ở round target trước khi log mới. **Wrapper `run_tests.sh` luôn pass flag này** để tránh duplicate khi re-run cùng round. Khi gọi tay mà muốn append-only thì bỏ flag này.

## Hỏi user trước khi chạy

Hỏi 2 thứ:
1. **Round nào**? Default 1. Nếu user đã chạy lần trước rồi muốn lưu thành Round 2 → hỏi.
2. **Tester name**? Default "Auto-Robot". Nếu user là QA thật thì hỏi tên (vd "Tho Le").

Skill cũng có thể dò: nếu user mention tên (vd "tôi là Tho Le, run test giúp"), dùng tên đó.

## File mapping (reference)

### `testcase_ocr.xlsx` — sheet `ocr testcase api`

| Round | Result | Bug ID | Tester | Test Date | Note |
|---|---|---|---|---|---|
| Round 1 | Col 8 (H) | Col 9 (I) | Col 10 (J) | Col 11 (K) | Col 12 (L) |
| Round 2 | Col 13 (M) | Col 14 (N) | Col 15 (O) | Col 16 (P) | Col 17 (Q) |
| Round 3 | Col 18 (R) | Col 19 (S) | Col 20 (T) | Col 21 (U) | Col 22 (V) |

- **Result**: `Pass` / `Fail` / `Impact` / `Not Run` — tô màu cell theo status.
- **Bug ID**: integer = STT bên `logbug.xlsx`. Trống nếu PASS/Not Run.
- **Tester**: từ `--tester` arg.
- **Test Date**: `date.today()` (Excel sẽ render dd/mm/yyyy).
- **Note**: failure message từ Robot (max 300 chars).

Map TC name → row trong xlsx: regex `^OCR_API-(\d+[a-z]?)\b` từ tên Robot match với `[OCR_API-N]` ở col A.

### `logbug.xlsx` — sheet `Logbug` (21 cols, header row 2)

Khi TC FAIL → append 1 row mới:

| Col | Field | Giá trị |
|---|---|---|
| 1 | STT | `max_stt + 1` (sequential) |
| 2 | Task | `OCR-CONTEST` (constant) |
| 3 | Chức năng | `OCR API - POST /api/v1/ocr/extract` (cho 01_functional; điều chỉnh cho suite khác) |
| 4 | Mô tả bug | `[OCR_API-N] <failure_message[:300]>` |
| 5 | Ảnh | (empty) |
| 6 | Urgent | `False` |
| 7 | Severity | derived: `Critical` (status 4xx unexpected) / `Major` (schema fail) / `Minior` (503 vendor) / `Normal` (else) — **giữ typo "Minior" cho khớp file user** |
| 8 | Kết quả mong muốn | Lấy từ col 6 (Expected Output) của TC trong `testcase_ocr.xlsx` |
| 9 | Ngày | `date.today()` |
| 10 | Thời gian xử lý bug | (empty) |
| 11 | Status | Formula: `=IF(AND(O{r}="Closed",S{r}="Fixed"),"Closed", IF(S{r}="Reject","Reject", IF(O{r}="Reopen","Reopen", IF(S{r}="Fixing","Fixing", IF(S{r}="Fixed","Fixed", IF(O{r}="New","New",))))))` |
| 12 | Bug | Formula: `=IF(AND(N{r}=TRUE(),R{r}=TRUE()),"Bug",)` |
| 13 | PIC (Test) | từ `--tester` |
| 14 | Bug (T) | `True` |
| 15 | Test status | `New` |
| 16 | PIC (Dev) | (empty — dev sẽ fill) |
| 17 | Team Dev | (empty) |
| 18 | Bug (D) | `True` |
| 19 | Dev status | (empty) |
| 20 | PIC cũ (Dev) | (empty) |
| 21 | Note | `failure_message[:500]` |

## Khi user muốn thay đổi behavior

| Yêu cầu user | Cách xử lý |
|---|---|
| "không log 503 vào bug" | Edit `update_results.py` — skip TC nếu message chứa "503" trong nhánh FAIL. |
| "dedupe bug nếu cùng TC đã log" | Trước khi append, scan logbug rows đã có TC ID trong description → reuse STT. |
| "clear Round 1 trước khi ghi lại" | Thêm flag `--clean` xoá data Round target trước khi ghi. |
| "đổi Task / Chức năng" | Edit hằng số `LOGBUG_TASK` / `LOGBUG_FUNCTION` trong script (hoặc thêm CLI flag). |
| "ghi cho suite api2_history (TC-99+)" | Sửa `LOGBUG_FUNCTION` thành `OCR API - GET /api/v1/ocr/history` hoặc auto-detect theo TC ID range trong `short_module()`. |

## Edge cases đã handle

- **TC ID không có trong xlsx** (vd TC-12b là ADD ngoài 133 manual TC) → warning, skip update, không crash.
- **SKIP status** → ghi "Not Run", không log bug.
- **TC name không match pattern `OCR_API-N`** → bỏ qua (vd test setup/teardown).
- **logbug.xlsx có rows trống ở giữa** → `find_logbug_next_row()` skip, tìm last STT thực sự.
- **Formula trong logbug** → ghi nguyên formula với `{row}` substitute đúng row number mới.

## Phụ thuộc

- venv: `.venv/bin/python` (xem `OCR/README.md` section Cài đặt)
- packages: `openpyxl>=3.1`, `robotframework>=7.0` — đã có trong `OCR/requirements.txt`
- Script path: `OCR/scripts/update_results.py` (KHÔNG di chuyển — `run_tests.sh` reference đường dẫn này)

## File output

Sau khi chạy, in summary stdout:
```
=== Round N updated ===
  Pass:    X
  Fail:    Y  (Y bug logged in Logbug)
    STT range: A → B
  Not Run: Z
  Missing in testcase_ocr.xlsx: M

  → OCR/resources/test_cases/testcase_ocr.xlsx
  → OCR/resources/test_cases/logbug.xlsx
```

User mở xlsx để xem (skill không tự open).
