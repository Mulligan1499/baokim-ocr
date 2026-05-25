# dedupe-logbug

Skill dọn các bug entry trùng lặp trong `logbug.xlsx`.

## Khi cần dùng

Sau nhiều lần re-run test, cùng 1 TC fail có thể bị log thành nhiều entry (vd TC-121 có STT 33, 44, 55, 66, 142). Mặc dù `update_results.py --clean-round` đã prevent duplicate cho lần tiếp theo, các duplicate tích luỹ trước đó cần clean thủ công.

## Cách dùng

```bash
# Xem trước (không sửa file)
.venv/bin/python OCR/scripts/dedupe_logbug.py

# Apply (xoá thật)
.venv/bin/python OCR/scripts/dedupe_logbug.py --apply
```

Hoặc qua Claude chat:
```
/dedupe-logbug
```

Hoặc nói tự nhiên: *"dọn bug duplicate trong logbug"*.

## Logic

- **Group** rows theo TC ID (extract từ cột "Mô tả bug").
- **Giữ STT lớn nhất** (entry mới nhất) cho mỗi TC.
- **Xoá** các entry STT nhỏ hơn.
- **Rebuild formula** Status/Bug cho rows còn lại.
- **Repoint Bug ID** trong testcase_ocr.xlsx Round 1/2/3 → STT giữ lại.

Manual bugs (STT 1-4, không có pattern `[OCR_API-N]`) **không bị động vào**.

## File ảnh hưởng

- `OCR/resources/test_cases/logbug.xlsx` (delete rows)
- `OCR/resources/test_cases/testcase_ocr.xlsx` (repoint Bug ID references)

## Best practice

1. Commit xlsx trước khi chạy (để rollback nếu cần)
2. Luôn dry-run trước
3. Sau apply, mở xlsx kiểm tra mắt thường vài TC quan trọng
