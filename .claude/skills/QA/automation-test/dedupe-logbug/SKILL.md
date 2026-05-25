---
name: dedupe-logbug
description: |
  [WHAT] Dọn dẹp các bug entry trùng lặp trong `OCR/resources/test_cases/logbug.xlsx` — group theo TC ID (pattern `[OCR_API-N]` trong cột "Mô tả bug"), giữ STT mới nhất per TC, xoá older. Đồng thời repoint Bug ID references trong `testcase_ocr.xlsx` Round 1/2/3 → STT giữ lại, và rebuild formula col Status/Bug sau khi delete.
  [WHEN] User nói: "xoá bug duplicate trong logbug", "dedupe logbug", "logbug có nhiều entry trùng cho cùng 1 TC", "dọn logbug", "gộp các bug entry trùng".
  [SKIP] Không dùng nếu chưa có duplicate (chạy dry-run sẽ báo "Nothing to dedupe"). Không xoá manual bugs (STT 1-4 — không có TC ID pattern trong description).
---

# dedupe-logbug

Skill này gọi script `OCR/scripts/dedupe_logbug.py` để dọn các bug entry trùng lặp tích luỹ qua nhiều lần re-run test.

## Khi cần skill này

`update_results.py --clean-round` (đã wire vào `run_tests.sh`) chỉ prevent duplicate cho lần run **tiếp theo**. Các bug duplicate **tích luỹ từ trước** (vd 5 entries cho TC-121 ở STT 33, 44, 55, 66, 142) cần dùng skill này để clean retroactively.

User invoke:
- Sau khi nhận ra logbug có nhiều entry cho cùng 1 TC
- Trước khi share logbug.xlsx cho dev (clean báo cáo)
- Khi setup `--clean-round` lần đầu (dọn legacy)

## Cách invoke

```bash
# Bước 1: Dry-run — xem sẽ xoá gì, KHÔNG sửa file
.venv/bin/python OCR/scripts/dedupe_logbug.py

# Bước 2: Apply — thực sự xoá
.venv/bin/python OCR/scripts/dedupe_logbug.py --apply
```

**Luôn dry-run trước**, confirm danh sách kept/removed STT đúng ý, rồi mới apply.

## Logic dedupe

1. Đọc `logbug.xlsx` sheet `Logbug`.
2. Group rows theo TC ID extract từ cột 4 ("Mô tả bug") bằng pattern `\[OCR_API-(\d+[a-z]?)\]`.
   - Rows KHÔNG có TC ID (vd manual bugs STT 1-4) → **bỏ qua, không đụng**.
3. Với mỗi TC có >1 entry:
   - **Giữ STT lớn nhất** (entry mới nhất theo timeline tạo).
   - **Mark xoá** các STT nhỏ hơn.
4. Delete rows bottom-up (để row number trên không shift sai).
5. **Rebuild formula** cho col 11 (Status) và col 12 (Bug) ở mọi row còn lại — vì `delete_rows` không tự update formula references → công thức `=IF(AND(O7=...))` sẽ trỏ sai row sau khi shift.
6. **Repoint Bug ID** trong `testcase_ocr.xlsx`:
   - Quét col 9 (Round 1), 14 (Round 2), 19 (Round 3).
   - Nếu giá trị Bug ID trùng 1 STT bị xoá → đổi sang STT giữ lại cho cùng TC.

## Output sau khi apply

```
✅ Deleted N duplicate rows from logbug.xlsx
✅ Repointed M Bug ID references in testcase_ocr.xlsx → kept STT
```

## Edge cases đã handle

- **Manual bugs (STT 1-4)** không có pattern `[OCR_API-N]` → bỏ qua, giữ nguyên.
- **TC có duy nhất 1 entry** → không xoá.
- **Formula `=IF(AND(O{r}=...))`** sau khi delete_rows → rebuild với row number mới.
- **Bug ID 0 cần repoint** → script vẫn chạy OK, in `Repointed 0`.

## Không dedupe theo các tiêu chí khác

Script CHỈ dedupe theo TC ID. Các trường hợp không xử lý:
- 2 bug khác TC nhưng cùng "Mô tả bug" → coi là khác bug, không gộp.
- 2 bug cùng TC ID nhưng khác lý do (1 cũ đã fix, 1 mới fail khác lý do) → vẫn gộp về STT mới nhất (mất context cũ — đó là điểm trade-off).

Nếu cần dedupe theo logic khác, edit `dedupe_logbug.py` (function `main()` chỗ group key).

## Liên quan

- `[[update-tc-results-from-log]]` — skill chính chạy sau Robot run, có flag `--clean-round` prevent duplicate cho FUTURE runs.
- `dedupe-logbug` chỉ dùng để dọn LEGACY duplicate hoặc khi xảy ra duplicate ngoài luồng auto.
