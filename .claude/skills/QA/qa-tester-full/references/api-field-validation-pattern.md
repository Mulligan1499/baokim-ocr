# API Field-by-Field Validation Pattern

Khi test API có nhiều field input, dùng pattern **validate từng trường (field-by-field)** thay vì viết test case theo flow chung. Mỗi field có một cụm test case riêng với standard checklist.

> Dùng kèm với `api-spec-to-testcase.md` (workflow tổng) và `security-injection-tests.md` (deep security).

---

## 1. Khi nào dùng pattern này

Áp dụng khi:
- API có ≥ 5 field input cần validate riêng biệt
- Spec API chi tiết về kiểu dữ liệu, max length, required/optional của từng field
- Cần kiểm thử exhaustive theo từng field (PCI-DSS, financial, payment API)
- Module có nhiều API liên quan, cần coverage validate đầy đủ

KHÔNG dùng cho:
- API đơn giản 1-3 field (viết flow test luôn cho gọn)
- API chỉ test happy path (không cần deep validate)
- Smoke test / regression nhẹ

---

## 2. Cấu trúc test case theo pattern field-by-field

### Cấu trúc 3 cấp trong cùng 1 sheet:

```
SECTION lớn (xanh nhạt):        I. API <tên API> — Validate từng field
  FIELD HEADER (vàng nhạt):       <field_name>  |  required/optional, kiểu, độ dài
    TEST CASE 1:                    [MOD-1]  KT để trống trường ...
    TEST CASE 2:                    [MOD-2]  KT chỉ là space
    TEST CASE 3:                    [MOD-3]  KT > max length
    ...
    TEST CASE N:                    [MOD-N]  KT request hợp lệ — verify DB
  FIELD HEADER:                   <field tiếp theo>  |  ...
    TEST CASE 1: ...
```

### Format mỗi test case:

| Cột | Nội dung |
|---|---|
| **Test Item** | `KT <điều gì>` ngắn gọn tiếng Việt (vd: `KT để trống trường request_id`) |
| **Pre-Condition** | Common: "Đã có JWT hợp lệ + Signature hợp lệ" |
| **Data Test** | Value cụ thể của field đang test (vd: `request_id = ""`) |
| **Step** | 1. Các field khác hợp lệ<br>2. `<field>` = `<value>`<br>3. Send request |
| **Expected Output** | `Response:\ncode: 422\nmessage: <thông báo>` |
| **Priority** | High / Medium / Low |

---

## 3. Cột "Data Test" — bắt buộc với API testing

API testing có một đặc trưng quan trọng: **giá trị input là phần quan trọng nhất** (khác UI testing tập trung vào hành vi click/nhập). Cột Data Test giúp tester:
- Copy thẳng giá trị vào Postman/curl không cần đọc lại Step
- Tách bạch "data" vs "thao tác" — dễ maintain khi spec đổi
- Tránh lặp "1. Các field khác hợp lệ\n2. field = X\n3. Send" trong cột Step

**Cấu trúc cột Data Test**:

| Trường hợp | Format cột Data Test |
|---|---|
| Test 1 field cụ thể | `<field_name> = <value>` (vd: `amount = -10000`) |
| Test nhiều sub-case của 1 field | Liệt kê a/b/c:<br>`a. amount = 0`<br>`b. amount = -1`<br>`c. amount = 9999999999` |
| Test happy path (verify body) | Paste nguyên body JSON copy được vào Postman |
| Test header (security) | `Headers:\n- Authorization: Bearer <token_invalid>` |
| Test encrypt/encode | Mô tả cách build data: `1. Encrypt({...}) với WRONG_KEY\n2. base64(iv+ciphertext)` |

---

## 4. Standard checklist validate cho 1 field

Mỗi field có một bộ test case chuẩn. Tùy kiểu dữ liệu, chọn các case phù hợp.

### 4.1 String field (required, có max length)

| # | Test Item | Data Test | Expected | Priority |
|---|---|---|---|---|
| 1 | KT để trống trường `<field>` | `<field> = ""` | 422 | High |
| 2 | KT không truyền field `<field>` | (không có field) | 422 | High |
| 3 | KT `<field>` = chỉ là space | `<field> = "     "` | 422 | Medium |
| 4 | KT `<field>` chứa space đầu cuối | `<field> = " value "` | Confirm BA (trim hay reject) | Medium |
| 5 | KT `<field>` có space ở giữa | `<field> = "val ue"` | 422 hoặc accept tùy BA | Medium |
| 6 | KT `<field>` chứa ký tự đặc biệt | `<field> = "@#$%^&*()"` | 422 | Medium |
| 7 | KT `<field>` chứa tiếng Việt có dấu | `<field> = "Tiếng Việt"` | 422 hoặc accept | Low |
| 8 | KT `<field>` > max ký tự | `<field> = "A" * (max+1)` | 422 | Medium |
| 9 | KT `<field>` = max ký tự (boundary) | `<field> = "A" * max` | success (không reject vì boundary) | Medium |
| 10 | KT `<field>` chứa SQL injection (boolean) | `<field> = "X' OR '1'='1"` | 422/401, không bypass, log SIEM | **High** |
| 11 | KT `<field>` chứa SQL injection (stacked) | `<field> = "X'; DROP TABLE ...; -- "` | 422, table còn nguyên | **High** |
| 12 | KT request hợp lệ — verify DB | Value chuẩn | success + DB lưu đúng field | High |

### 4.2 String field là FK (merchant_code, client_id...)

Bổ sung ngoài checklist 4.1:

| Test Item | Data Test | Expected |
|---|---|---|
| KT `<field>` không tồn tại trong DB | `<field> = "NOT_EXIST_001"` | 422/404 |
| KT `<field>` case-sensitive check | Sub-case: "VALUE" / "value" / "Value" | Confirm BA case-sensitive |
| KT `<field>` trạng thái khác Active | `<field> = "INACTIVE_xxx"` | 422 hoặc code báo inactive |
| KT `<field>` thuộc tenant khác (cross-tenant) | Token user A, dùng `<field>` của user B | 403/422 |

### 4.3 String field là unique (request_id, idempotency_key...)

Bổ sung ngoài checklist 4.1:

| Test Item | Data Test | Expected |
|---|---|---|
| KT `<field>` đã tồn tại (duplicate) | `<field>` = giá trị đã có trong DB | 409/422, không insert bản ghi mới |

### 4.4 Number field (amount, quantity...)

| # | Test Item | Data Test | Expected |
|---|---|---|---|
| 1 | KT để trống trường | `field = ""` | 422 |
| 2 | KT không truyền field | (không có field) | 422 |
| 3 | KT field = null | `field = null` | 422 |
| 4 | KT field = ký tự chữ | `field = "abcxyz"` | 422 |
| 5 | KT field = ký tự đặc biệt | `field = "^%&*$"` | 422 |
| 6 | KT field = chuỗi 0 (000000) | `field = "000000"` | 422 |
| 7 | KT field = số âm | `field = -1000` | 422 |
| 8 | KT field = số thập phân | `field = 1000.5` | 422 (nếu chỉ accept int) |
| 9 | KT field = 0 | `field = 0` | 422 |
| 10 | KT field = 1 (boundary min) | `field = 1` | Confirm BA min |
| 11 | KT field = max (boundary) | `field = <max_value>` | success |
| 12 | KT field > max | `field = <max+1>` | 422 |
| 13 | KT field integer overflow | `field = 9999999999999999999` | 422, không crash |
| 14 | KT field chứa SQL/NoSQL injection | `field = "1; DROP TABLE..."` hoặc `field = {"$gt": 0}` | 422 |
| 15 | KT request hợp lệ — verify DB | `field = <valid>` | success + DB lưu đúng |

### 4.5 Enum field (type = 1/2, status = "active"/"inactive"...)

| # | Test Item | Data Test | Expected |
|---|---|---|---|
| 1 | KT không truyền field | (không có) | 422 hoặc dùng default |
| 2 | KT field = giá trị ngoài enum | `field = <out_of_enum>` | 422 |
| 3 | KT field = giá trị âm | `field = -1` | 422 |
| 4 | KT field = kiểu sai (string thay vì number) | `field = "abc"` | 422 |
| 5+ | KT mỗi enum value hợp lệ | Mỗi enum 1 TC | success + behavior tương ứng |

### 4.6 URL field (url_success, callback_url, webhook_url...)

| # | Test Item | Data Test | Expected |
|---|---|---|---|
| 1 | KT để trống | `url = ""` | 422 |
| 2 | KT không phải URL hợp lệ | `url = "not-a-url"` | 422 |
| 3 | KT scheme `javascript:` (XSS) | `url = "javascript:alert(1)"` | 422 (XSS prevention) |
| 4 | KT scheme `http://` (không phải https) | `url = "http://..."` | Confirm BA bắt buộc HTTPS |
| 5 | KT > max length | `url = "A" * (max+1)` | 422 |
| 6 | KT domain không thuộc whitelist | `url = "https://evil.com/..."` | 422 |
| 7 | KT XSS payload trong query string | `url = "https://.../?<script>alert(1)</script>"` | URL-encode hoặc reject, XSS không thực thi |
| 8 | KT open redirect attack | `url = "https://merchant.com//evil.com/redirect"` | 422 hoặc sanitize |
| 9 | KT SSRF — URL internal | `url = "http://localhost:6379/"` hoặc `http://169.254.169.254/` | 422, không follow internal URL |

### 4.7 Email field

| # | Test Item | Data Test | Expected |
|---|---|---|---|
| 1 | KT thiếu `@` | `email = "abcxyz.com"` | 422 hoặc accept tùy validation |
| 2 | KT thiếu domain | `email = "user@"` | 422 |
| 3 | KT > max length | `email = "a"*250 + "@x.com"` | 422 |
| 4 | KT chứa SQL injection | `email = "test@a.com'; DROP TABLE...; -- "` | 422, table còn nguyên |
| 5 | KT email hợp lệ | `email = "test@example.com"` | success + DB lưu |

### 4.8 DateTime field (format YYYY-MM-DD HH:mm:ss)

| # | Test Item | Data Test | Expected |
|---|---|---|---|
| 1 | KT để trống | `field = ""` | 422 |
| 2 | KT chỉ là space | `field = "                "` | 422 |
| 3 | KT chứa ký tự đặc biệt | `field = "@#$"` | 422 |
| 4 | KT sai định dạng (slash) | `field = "2026/01/15 14:00:00"` | 422 |
| 5 | KT sai định dạng DD-MM-YYYY | `field = "15-01-2026 14:00:00"` | 422 |
| 6 | KT định dạng ISO 8601 | `field = "2026-01-15T14:00:00Z"` | 422 |
| 7 | KT overflow (month=13, day=40...) | `field = "2026-13-40 25:99:99"` | 422 |
| 8 | KT thời gian thực | `field = now` | success |
| 9 | KT chậm hơn thực 5 phút | `field = now - 5 min` | Confirm BA threshold |
| 10 | KT chậm hơn thực 1 giờ | `field = now - 1 hour` | Reject (replay window) |
| 11 | KT nhanh hơn thực 1 giờ | `field = now + 1 hour` | Reject (time skew) |
| 12 | KT chứa SQL injection | `field = "2026-01-15 14:00:00' OR '1'='1"` | 422 |

---

## 5. Style tiếng Việt "KT <điều gì>"

### Quy tắc đặt tên test item theo pattern này:

✅ **Đúng style** (ngắn gọn, bắt đầu bằng "KT"):
- `KT để trống trường request_id`
- `KT request_id > 100 ký tự`
- `KT request_id đã tồn tại`
- `KT amount = số âm`
- `KT card_data encrypt bằng SAI secret_key`

❌ **Sai style** (lẫn với style "Verify..." dùng cho flow test):
- `Verify validation request_id rỗng` — chỉ dùng cho flow test
- `Test request_id null` — không bắt đầu bằng động từ
- `1. Request ID empty` — không đúng format

### Khi nào dùng "KT" vs "Verify":

| Style | Khi nào | Ví dụ |
|---|---|---|
| **KT** (Kiểm thử) | Field validation, mỗi field nhiều case nhỏ | `KT để trống email` |
| **Verify** | Flow test, business logic, integration | `Verify đăng nhập thành công với credentials hợp lệ` |

---

## 6. Mixed style trong 1 sheet

Một sheet có thể chứa MIX cả 2 style:

```
SECTION "I. API X — Validate từng field"        ← field_validation style
  field_1 | required ...
    KT để trống ...
    KT > max length ...
  field_2 | optional ...
    ...

SECTION "II. API X — Flow chính"                ← plain style
  TC: Verify happy path ...
  TC: Verify error case ...

SECTION "III. Security Injection Deep"          ← plain style
  TC: KT Time-based blind SQL ...
  TC: KT NoSQL operator injection ...
```

Generator script `generate_api_testcase_xlsx.py` auto-detect style:
- Section có key `field_groups` → render field_validation style
- Section có key `test_cases` → render plain style

---

## 7. Ví dụ end-to-end

**Input**: Spec API Authentication thẻ tín dụng với 12 field

**Output sections**:

```python
sections = [
    {
        "name": "I. API Authentication — Validate từng field",
        "field_groups": [
            {
                "field": "request_id",
                "required": "required, String(100)",
                "test_cases": [
                    {"test_item": "KT để trống trường request_id", ...},
                    {"test_item": "KT request_id > 100 ký tự", ...},
                    {"test_item": "KT request_id đã tồn tại", ...},
                    {"test_item": "KT request_id chứa SQL injection", ...},
                    {"test_item": "KT request_id hợp lệ — verify DB", ...},
                    # ... 10-15 TC mỗi field
                ]
            },
            {"field": "request_time", "required": "required, String(20)", "test_cases": [...]},
            {"field": "amount", "required": "required, Number", "test_cases": [...]},
            # ... mỗi field 1 group
        ]
    },
    {
        "name": "II. Flow chính (Happy path + Bypass + UCOF)",
        "test_cases": [
            {"test_item": "Verify happy path thẻ Frictionless success", ...},
            # ... flow test
        ]
    },
    {
        "name": "III. Security Injection Deep Tests",
        "test_cases": [
            {"test_item": "KT Time-based blind SQL injection", ...},
            # ... xem security-injection-tests.md
        ]
    }
]
```

**Số lượng TC ước tính**: 1 field × 10-15 TC × 12 field = **120-180 TC** chỉ riêng field validation, chưa kể flow test và security deep.

---

## 8. Anti-pattern

| Anti-pattern | Sửa |
|---|---|
| Gộp validate field vào flow test (vd "Verify with invalid email") | Tách thành section field validation riêng |
| Test item tiếng Anh lẫn tiếng Việt | Dùng nhất quán "KT" tiếng Việt cho field validation |
| Bỏ qua case "không truyền field" (chỉ test "để trống") | Hai case khác nhau: missing key vs empty string |
| Không có cột Data Test, value nằm trong Step | API testing bắt buộc tách Data Test ra cột riêng |
| Không có case "request hợp lệ — verify DB" cuối mỗi field | Phải có positive case + DB verify để đóng group |
| Bỏ qua SQL injection vì "đã có WAF" | Vẫn phải test trên API layer (defense in depth) |
| Field FK không test cross-tenant | Bắt buộc test token user A + resource user B |
