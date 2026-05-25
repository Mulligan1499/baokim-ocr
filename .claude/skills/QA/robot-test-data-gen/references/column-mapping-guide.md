# Column Mapping Guide

Hướng dẫn chi tiết cách map Excel column → CSV column. Skill phải robust với variant header.

---

## 1. Algorithm tổng quát

```
Step 1: Read row 1 (header row) của Excel
Step 2: Cho mỗi cell trong row 1:
        - Normalize: lowercase, strip whitespace, replace _/- với space
        - Match với pattern list (theo thứ tự ưu tiên cao → thấp)
        - Lấy match đầu tiên với confidence score >= threshold
Step 3: Build mapping dict: {csv_column: excel_column_index}
Step 4: Nếu có column quan trọng MISSING (Case_id, Description, expected_code) → hỏi user
Step 5: Nếu mapping ambiguous (2 cột match cùng pattern) → hỏi user chọn
```

**Confidence threshold:** ≥ 70% cho auto-map, < 70% hỏi user.

---

## 2. Pattern detail từng column

### 2.1 `Case_id` (REQUIRED)

| Confidence | Regex pattern | Ví dụ Excel header match |
|---|---|---|
| 100% | `^(tc[_ -]?id\|test[_ -]?case[_ -]?id)$` | `TC_ID`, `Test Case ID`, `tc id` |
| 95% | `^(case[_ -]?id\|test[_ -]?id)$` | `Case_ID`, `Test ID` |
| 85% | `^(mã test\|mã tc\|số tc\|stt test)$` | `Mã test`, `Số TC` |
| 75% | `^id$` | `ID` |
| 50% | `^stt$` | `STT` (cần confirm — STT có thể là index) |

**Edge case:**
- Nếu không tìm thấy → gen tự động: `TC_<MODULE>_001`, `TC_<MODULE>_002`...
- Module name lấy từ tên feature anh cung cấp (vd `LOGIN`, `ORDER`)

### 2.2 `Descriptions` (REQUIRED)

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^(description\|descriptions)$` | `Description`, `Descriptions` |
| 95% | `^(test[_ -]?name\|test[_ -]?title\|test[_ -]?description)$` | `Test Name`, `Test Description` |
| 90% | `^(mô tả\|tên test\|tiêu đề)$` | `Mô tả`, `Tên test` |
| 80% | `^(title\|name\|summary)$` | `Title`, `Summary` |
| 70% | `^(scenario\|kịch bản)$` | `Scenario`, `Kịch bản` |

**Edge case:**
- Nếu Excel chỉ có "Test Steps" mà không có "Description" → tạo Description từ first line của Test Steps (max 80 ký tự)

### 2.3 Test Steps (input để suy field_name + test_value)

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^(test[_ -]?steps?\|steps)$` | `Test Steps`, `Steps` |
| 95% | `^(các bước\|quy trình test\|step thực hiện)$` | `Các bước`, `Quy trình test` |
| 85% | `^(precondition[_ -]?and[_ -]?steps\|action)$` | `Action` |
| 70% | `^(input\|data\|test[_ -]?data)$` | `Input`, `Test Data` |

Nếu Excel có sẵn cột `field_name` + `test_value` riêng → skip suy luận, dùng trực tiếp.

### 2.4 Expected Result (input để suy expected_code + expected_message)

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^expected[_ -]?(result\|results)$` | `Expected Result`, `Expected Results` |
| 95% | `^(expected\|outcome\|expected[_ -]?outcome)$` | `Expected`, `Outcome` |
| 90% | `^(kết quả mong đợi\|kết quả expect\|kqmd)$` | `Kết quả mong đợi`, `KQMD` |
| 80% | `^(result\|kết quả)$` | `Result`, `Kết quả` |

### 2.5 `expected_code` (REQUIRED)

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^expected[_ -]?(status\|code\|status[_ -]?code\|http[_ -]?code)$` | `Expected_Status`, `Expected Status Code` |
| 95% | `^(http[_ -]?status\|response[_ -]?code\|status[_ -]?expect)$` | `HTTP Status`, `Response Code` |
| 85% | `^(status\|http\|code)$` | `Status`, `Code` |

**Nếu không có cột riêng → parse từ Expected Result column.** Xem `references/sentinel-value-guide.md` mục "Parse expected_code".

### 2.6 `expected_message`

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^expected[_ -]?(error\|error[_ -]?code\|message\|error[_ -]?message)$` | `Expected_Error_Code` |
| 95% | `^(error[_ -]?code\|error[_ -]?message\|err[_ -]?code)$` | `Error Code`, `Error Message` |
| 85% | `^(mã lỗi\|thông báo lỗi\|message)$` | `Mã lỗi`, `Message` |

**Nếu không có → parse từ Expected Result column.**

### 2.7 `Tag`

| Confidence | Regex pattern | Ví dụ |
|---|---|---|
| 100% | `^tags?$` | `Tag`, `Tags` |
| 90% | `^(labels?\|nhãn)$` | `Label`, `Labels`, `Nhãn` |
| 80% | `^(priority\|type\|category\|loại\|loại test)$` | `Priority`, `Type`, `Loại test` |
| 70% | `^(severity\|test[_ -]?type)$` | `Severity`, `Test Type` |

**Nếu không có → auto-gen Tag từ Description.** Xem mục "Tag auto-gen heuristic" trong SKILL.md section 7.

---

## 3. Mapping mặc định (output template)

Sau khi detect, in mapping ra cho user review:

```
Column mapping detected:
  Case_id           ← Cột B  "TC_ID"           (confidence 100%)
  Descriptions      ← Cột C  "Description"     (confidence 100%)
  Test Steps        ← Cột D  "Test Steps"      (confidence 100%) — sẽ parse để suy field_name + test_value
  Expected Result   ← Cột E  "Expected Result" (confidence 100%) — sẽ parse để suy expected_code + expected_message
  Tag               ← Cột F  "Priority"        (confidence 80%) — đoán

Continue? (y/n/edit)
```

Nếu user reply `edit` → cho phép user manually map column index → CSV field.

---

## 4. Edge case xử lý

### 4.1 Excel có merged cell

Lỗi phổ biến: header chia nhiều dòng do merged cell.

**Solution:** Đọc cả row 1 + row 2, ghép lại nếu row 1 cell merge xuống. Dùng `openpyxl` để detect merged range.

### 4.2 Excel có nhiều sheet

Lỗi phổ biến: file Excel có sheet "Cover", "Index", "Test Cases", "Bug List"...

**Solution:**
1. List tất cả sheet name
2. Tìm sheet có header match patterns ở section 2 (cần ít nhất Case_id + Description)
3. Nếu có nhiều sheet match → hỏi user chọn
4. Nếu không match sheet nào → hỏi user chỉ định

### 4.3 Header không ở row 1

Lỗi phổ biến: row 1 là title, row 2 là note, row 3 mới là header.

**Solution:**
1. Scan top 5 rows
2. Row nào có ≥ 3 cell match patterns ở section 2 → đó là header row
3. Nếu không tìm thấy → hỏi user row số mấy

### 4.4 Cell có line break trong nội dung

Test Steps thường có `\n` ngăn cách các bước.

**Solution:**
- Split theo `\n` để parse từng step
- Mỗi step phân tích riêng (xem heuristic suy field_name ở section 6.1 của SKILL.md)
- Multi-step trong 1 TC → có thể là E2E flow (xem `references/e2e-detection-guide.md`)

### 4.5 Column tên giống nhau

Vd: cả `Test Description` và `Description` cùng có trong sheet.

**Solution:**
- Lấy column match với confidence cao nhất
- Nếu tie → hỏi user

---

## 5. Confirmation prompt template

Sau khi detect mapping, in ra format này cho user confirm:

```
📋 Đã đọc Excel: <filename> | Sheet: <sheet_name> | Total rows: <N>

Column mapping:
  ┌─────────────────────┬────────────────────────────┬──────────────┐
  │ CSV column          │ Excel column               │ Confidence   │
  ├─────────────────────┼────────────────────────────┼──────────────┤
  │ Case_id             │ Cột B "TC_ID"              │ ✅ 100%       │
  │ Descriptions        │ Cột C "Description"        │ ✅ 100%       │
  │ (suy field_name)    │ Cột D "Test Steps"         │ ✅ 100%       │
  │ (suy expected_*)    │ Cột E "Expected Result"    │ ✅ 100%       │
  │ Tag                 │ Cột F "Priority"           │ ⚠️ 80%        │
  └─────────────────────┴────────────────────────────┴──────────────┘

Mapping OK chưa anh? (y / n / edit cột nào)
```
