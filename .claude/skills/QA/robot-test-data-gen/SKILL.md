---
name: robot-test-data-gen
description: Convert file Excel manual test case (do người dùng viết hoặc skill qa-tester gen ra) thành file CSV test data cho Robot Framework auto test theo pattern single-field validation. Dùng khi anh nói "gen CSV từ test case", "convert test case manual sang data-driven", "tạo file data cho auto test", "build test data từ excel", "gen file data CSV cho feature". Skill flexible với column variant của Excel (đoán từ header phổ biến TC_ID, Test Steps, Expected Result), tự tách multi-field TC thành nhiều row single-field, và flag warning cho E2E flow TC (skip không gen). Output CSV chuẩn 7 cột: Case_id, Descriptions, field_name, test_value, expected_code, expected_message, Tag.
---

# Robot Test Data Gen — Skill chuyên gen CSV từ Excel manual test case

Skill này có **MỘT** việc duy nhất: chuyển Excel manual test case → CSV test data theo pattern single-field validation của Robot Framework auto test.

KHÔNG viết script .robot, KHÔNG setup project, KHÔNG gen keyword. Anh muốn làm những việc đó dùng skill khác (`robot-script-writer`, `robot-framework-automation`).

## 1. Khi nào dùng skill này

✅ Dùng khi:
- Anh đã có file Excel manual test case (từ `qa-tester` skill hoặc tự viết)
- Cần convert sang CSV test data để Robot Framework đọc qua DataDriver
- Mỗi test case manual nhắm test 1 field cụ thể (validation, boundary, edge case)

❌ KHÔNG dùng khi:
- Cần viết script .robot test suite → dùng skill `robot-script-writer`
- Cần setup project Robot Framework mới → dùng skill `robot-framework-automation`
- Test case là E2E flow (nhiều API kết hợp) — skill này sẽ skip và flag warning để anh viết tay

## 2. Workflow chuẩn

```
Bước 1: Anh đưa file Excel manual test case (.xlsx)
            ↓
Bước 2: Skill đọc Excel, detect column mapping
            ↓
Bước 3: Với mỗi row trong Excel:
        - Classify: single-field validation / multi-field / E2E flow
        - Single-field → 1 CSV row
        - Multi-field   → tách thành N CSV row (1 row/field)
        - E2E flow      → SKIP, ghi vào warnings.md
            ↓
Bước 4: Output:
        - <feature>_data.csv     (data test single-field)
        - <feature>_warnings.md  (list E2E TC bị skip + lý do)
        - Conversion report      (summary trên chat)
```

## 3. Format CSV output (BẮT BUỘC)

7 cột chuẩn, đúng thứ tự, KHÔNG đổi:

```csv
Case_id,Descriptions,field_name,test_value,expected_code,expected_message,Tag
```

| Cột | Required | Quy tắc |
|---|---|---|
| `Case_id` | ✅ | Format `TC_<MODULE>_<NNN>` (vd `TC_LOGIN_001`). Giữ nguyên từ Excel nếu có, gen mới nếu thiếu |
| `Descriptions` | ✅ | Mô tả ngắn 1 câu — giữ nguyên từ Excel hoặc rewrite ngắn gọn từ "Test Steps + Expected Result" |
| `field_name` | ⬜ | Field cần test (vd `username`, `customer.email`). RỖNG nếu là happy path |
| `test_value` | ⬜ | Value áp vào field. Hỗ trợ sentinel `<EMPTY>`, `<NULL>`, `<MISSING>` |
| `expected_code` | ✅ | HTTP status code expect (vd `200`, `400`, `401`) |
| `expected_message` | ⬜ | Error code/message expect (vd `USERNAME_REQUIRED`). Rỗng nếu success case |
| `Tag` | ⬜ | Tags ngăn cách bằng `;` (vd `positive;critical;smoke`) |

## 4. Sentinel value convention

Khi convert "test value" từ Excel sang CSV `test_value`:

| Excel ghi | → | CSV `test_value` |
|---|---|---|
| "để trống", "rỗng", "empty string", "" | → | `<EMPTY>` |
| "null", "NULL", "không có giá trị" | → | `<NULL>` |
| "thiếu field", "missing field", "không truyền", "không gửi" | → | `<MISSING>` |
| Giá trị cụ thể (vd "abc", "123") | → | giữ nguyên |
| Happy path (field rỗng trong column `field_name`) | → | để trống |

## 5. Quy tắc xử lý từng loại test case

### 5.1 Single-field validation TC

TC manual nhắm test **1 field cụ thể** → tạo **1 CSV row**.

Ví dụ Excel:
| TC_ID | Description | Test Steps | Expected Result |
|---|---|---|---|
| TC_LOGIN_002 | Username rỗng | POST /login với username="" | 400 USERNAME_REQUIRED |

→ CSV:
```csv
TC_LOGIN_002,Username rỗng,username,<EMPTY>,400,USERNAME_REQUIRED,negative;validation
```

### 5.2 Multi-field TC

TC manual ghi test **nhiều field cùng lúc** → **TÁCH** thành N CSV row, mỗi row test 1 field.

Ví dụ Excel:
| TC_ID | Description | Test Steps | Expected Result |
|---|---|---|---|
| TC_LOGIN_005 | Thiếu username và password | POST /login không có username, không có password | 400 USERNAME_REQUIRED |

→ CSV (tách 2 row):
```csv
TC_LOGIN_005a,Thiếu username (tách từ TC_LOGIN_005),username,<MISSING>,400,USERNAME_REQUIRED,negative;validation
TC_LOGIN_005b,Thiếu password (tách từ TC_LOGIN_005),password,<MISSING>,400,PASSWORD_REQUIRED,negative;validation
```

**Quy tắc tách:**
- Suffix `a`, `b`, `c` vào Case_id gốc
- `Descriptions` ghi rõ "tách từ TC_<original>" để traceability
- `expected_code` áp dụng cho từng field riêng (có thể differ nếu spec API chấp nhận check theo thứ tự field)

### 5.3 Happy path TC

TC manual test toàn bộ field hợp lệ → CSV row với `field_name` và `test_value` để **rỗng**.

Ví dụ Excel:
| TC_ID | Description | Test Steps | Expected Result |
|---|---|---|---|
| TC_LOGIN_001 | Login hợp lệ | POST /login với credential đúng | 200, return access_token |

→ CSV:
```csv
TC_LOGIN_001,Happy path — login với credential hợp lệ,,,200,,positive;smoke;critical
```

### 5.4 E2E flow TC (SKIP)

TC manual gồm **nhiều API call kết hợp** → SKIP, ghi vào `<feature>_warnings.md`.

**Cách detect E2E flow:**
- Test Steps có nhiều bước, mỗi bước call 1 API khác nhau
- Description chứa từ khóa: "quy trình", "luồng", "flow", "E2E", "đầy đủ"
- Test Steps có sequence: "B1: ... B2: ... B3: ..." trên nhiều endpoint

Ví dụ Excel:
| TC_ID | Description | Test Steps | Expected Result |
|---|---|---|---|
| TC_E2E_001 | Tạo order và thanh toán đầy đủ | B1: POST /orders, B2: POST /payments, B3: POST /payments/capture | Order = CAPTURED |

→ KHÔNG gen CSV row, thay vào đó ghi vào `<feature>_warnings.md`:

```markdown
## E2E Flow Test Cases (cần viết tay)

Skill đã skip các test case sau vì là E2E flow (nhiều API kết hợp).
Anh cần viết tay trong file .robot theo pattern flow keyword (xem skill `robot-script-writer`).

### TC_E2E_001 — Tạo order và thanh toán đầy đủ
- Steps: B1: POST /orders → B2: POST /payments → B3: POST /payments/capture
- Expected: Order = CAPTURED
- Suggest flow keyword: `Complete Payment Flow Successfully`
```

## 6. Đoán column mapping từ Excel header

Skill phải robust với column header variant. Dùng heuristic match (case-insensitive, regex):

| CSV column | Excel header pattern (regex case-insensitive) |
|---|---|
| `Case_id` | `tc[_ -]?id`, `test[_ -]?case[_ -]?id`, `id`, `case[_ -]?id`, `mã test`, `số tc` |
| `Descriptions` | `description`, `mô tả`, `tên test`, `test[_ -]?name`, `title`, `tiêu đề` |
| (Test Steps) | `test[_ -]?steps?`, `steps`, `các bước`, `quy trình test` |
| (Expected Result) | `expected[_ -]?result`, `expected`, `kết quả mong đợi`, `kết quả expect` |
| `expected_code` | `expected[_ -]?(status\|code)`, `status[_ -]?code`, `http[_ -]?code`, `response[_ -]?code` |
| `expected_message` | `expected[_ -]?(error\|message)`, `error[_ -]?code`, `error[_ -]?message`, `mã lỗi` |
| `Tag` | `tags?`, `nhãn`, `priority`, `type`, `loại test` |

**Logic mapping:**
1. Đọc row 1 (header)
2. Với mỗi cột Excel, match với patterns trên (lấy match đầu tiên)
3. Nếu có cột "Test Steps" + "Expected Result" mà không có `field_name`/`test_value` riêng → suy ra từ steps + expected (xem 6.1)
4. Nếu Excel đã có sẵn cột `field_name` + `test_value` → giữ nguyên
5. Nếu mapping không chắc chắn (< 70% confidence) → hỏi user xác nhận

### 6.1 Suy field_name + test_value từ Test Steps + Expected Result

Pattern phổ biến trong Test Steps:

| Test Steps mô tả | → | field_name | test_value |
|---|---|---|---|
| "POST /login với username=`abc`" | → | `username` | `abc` |
| "POST /login để username rỗng" | → | `username` | `<EMPTY>` |
| "POST /login không có field password" | → | `password` | `<MISSING>` |
| "POST /login với amount = -100" | → | `amount` | `-100` |
| "Set email = null" | → | `email` | `<NULL>` |
| "POST với customer.email = invalid" | → | `customer.email` | `invalid` |

Pattern phổ biến trong Expected Result:

| Expected Result mô tả | → | expected_code | expected_message |
|---|---|---|---|
| "200 OK" | → | `200` | (empty) |
| "400 BadRequest USERNAME_REQUIRED" | → | `400` | `USERNAME_REQUIRED` |
| "Trả lỗi 401 INVALID_CREDENTIALS" | → | `401` | `INVALID_CREDENTIALS` |
| "Status 422, error code AMT_TOO_SMALL" | → | `422` | `AMT_TOO_SMALL` |
| "Tạo thành công, trả về order_id" | → | `201` | (empty) |

Chi tiết heuristic + edge case: xem `references/column-mapping-guide.md`.

## 7. Tag mapping convention

Skill auto-gen Tag từ Description hoặc Type column (nếu có):

| Pattern trong TC | → | Tag thêm vào |
|---|---|---|
| Description có "happy path", "thành công", "hợp lệ" | → | `positive` |
| Description có "lỗi", "sai", "rỗng", "thiếu", "invalid" | → | `negative` |
| Description có "boundary", "biên", "max", "min" | → | `boundary` |
| Description có "security", "SQL injection", "XSS" | → | `negative;security` |
| Description có "smoke", "critical" hoặc Priority=High | → | `smoke;critical` |
| Description có "validation", "định dạng", "format" | → | `validation` |
| Description có "idempotent" | → | `idempotency` |
| Default (không match gì) | → | `regression` |

Tag phân cách bằng `;` (semicolon) — KHÔNG dùng dấu phẩy.

## 8. Quy trình thực hiện (step-by-step cho Claude)

Khi nhận yêu cầu gen CSV:

1. **Confirm input**: hỏi anh path file Excel + tên feature (nếu chưa rõ).

2. **Đọc Excel**: dùng `openpyxl` hoặc pandas. Xem ví dụ trong `scripts/convert_excel_to_csv.py`.

3. **Detect column mapping**: apply heuristic ở section 6. Print mapping cho user review trước khi convert.

4. **Convert từng row**:
   - Classify loại TC (single-field / multi-field / E2E / happy path)
   - Apply rule tương ứng
   - Build CSV row

5. **Generate output files**:
   - `<feature>_data.csv` — file chính
   - `<feature>_warnings.md` — nếu có E2E TC bị skip

6. **Print conversion report** trên chat:
   - Tổng row Excel input
   - Tổng row CSV output (có thể nhiều hơn nếu split multi-field)
   - Số E2E skip
   - Warnings nếu có (column mapping không chắc, sentinel value đoán không tự tin, ...)

7. **Sử dụng `present_files`** để share file CSV + warnings.md.

## 9. Reference files (đọc thêm khi cần)

- `references/column-mapping-guide.md` — heuristic chi tiết, edge case mapping
- `references/sentinel-value-guide.md` — quy ước sentinel, ví dụ thực tế
- `references/e2e-detection-guide.md` — cách detect E2E flow TC
- `examples/` — input Excel mẫu + CSV output tương ứng cho 3 domain: auth, order, payment
- `scripts/convert_excel_to_csv.py` — script Python reference implementation
