# Sentinel Value Guide

Hướng dẫn convert nội dung Test Steps + Expected Result trong Excel → sentinel values cho CSV.

---

## 1. Convention sentinel

| Sentinel | JSON behavior | Khi nào dùng |
|---|---|---|
| `<EMPTY>` | `"field": ""` | Test field = empty string |
| `<NULL>` | `"field": null` | Test field = JSON null |
| `<MISSING>` | (field bị remove khỏi payload) | Test required field validation |

---

## 2. Pattern detection — `<EMPTY>` (empty string)

### 2.1 Tiếng Việt

Match patterns (case-insensitive):
- "để trống"
- "trống"
- "bỏ trống"
- "rỗng"
- "để rỗng"
- "không nhập"
- "không điền"
- "không có giá trị"  ⚠️ ambiguous — có thể là `<NULL>`, cần xem context
- "empty"
- "blank"

### 2.2 Tiếng Anh

- `empty`
- `empty string`
- `blank`
- `no value entered`
- `field is empty`
- `""`  (literal empty quotes)
- `''`

### 2.3 Trong API context

| Test Steps ghi | → sentinel |
|---|---|
| `username = ""` | `<EMPTY>` |
| `set username to empty string` | `<EMPTY>` |
| `gửi username với giá trị rỗng` | `<EMPTY>` |
| `payload: {"username": ""}` | `<EMPTY>` |
| `để trống field username` | `<EMPTY>` |

---

## 3. Pattern detection — `<NULL>` (JSON null)

### 3.1 Tiếng Việt

- "null"
- "NULL"
- "giá trị null"
- "set null"
- "trả null"

### 3.2 Tiếng Anh

- `null`
- `NULL`
- `nil` (rare)
- `set to null`
- `pass null`

### 3.3 Trong API context

| Test Steps ghi | → sentinel |
|---|---|
| `username = null` | `<NULL>` |
| `set email to null` | `<NULL>` |
| `payload: {"username": null}` | `<NULL>` |
| `gửi field email = null` | `<NULL>` |

⚠️ **Phân biệt với `<MISSING>`:**
- `<NULL>` = field có trong payload nhưng value là `null`
- `<MISSING>` = field không tồn tại trong payload

---

## 4. Pattern detection — `<MISSING>` (remove field)

### 4.1 Tiếng Việt

- "thiếu field"
- "thiếu trường"
- "không có field"
- "không truyền"
- "không gửi"
- "không có trong payload"
- "không truyền field"
- "bỏ qua field"
- "missing field"

### 4.2 Tiếng Anh

- `missing`
- `missing field`
- `not provided`
- `field not sent`
- `omit field`
- `without field`
- `no <field> in payload`

### 4.3 Trong API context

| Test Steps ghi | → sentinel |
|---|---|
| `không truyền username` | `<MISSING>` |
| `payload không có username` | `<MISSING>` |
| `thiếu field email` | `<MISSING>` |
| `omit phone field` | `<MISSING>` |
| `request without authorization header` | `<MISSING>` |

---

## 5. Pattern detection — Literal value

Nếu không match `<EMPTY>` / `<NULL>` / `<MISSING>` → giữ giá trị literal.

### 5.1 Ví dụ literal

| Test Steps ghi | → test_value |
|---|---|
| `username = "qa@test.com"` | `qa@test.com` |
| `set amount = -100` | `-100` |
| `password = "abc"` (quá ngắn) | `abc` |
| `email = "invalid-format"` | `invalid-format` |
| `amount = 999999999` | `999999999` |
| `currency = "XYZ"` | `XYZ` |

### 5.2 Edge case: special characters

| Test Steps | → test_value (giữ nguyên trong CSV) |
|---|---|
| `username = "' OR '1'='1"` (SQL injection) | `' OR '1'='1` |
| `username = "<script>alert(1)</script>"` (XSS) | `<script>alert(1)</script>` |
| `description = "Đây là mô tả"` (Unicode) | `Đây là mô tả` |

⚠️ Khi write CSV — phải escape đúng:
- Cell chứa `,` → wrap trong `"..."`
- Cell chứa `"` → escape thành `""`
- Sentinel `<EMPTY>`, `<NULL>`, `<MISSING>` KHÔNG quote

---

## 6. Parse `expected_code` từ Expected Result

### 6.1 Regex patterns (theo thứ tự ưu tiên)

| Priority | Regex | Match example | Output |
|---|---|---|---|
| 1 | `\b(2\d{2}\|4\d{2}\|5\d{2})\b` | "Trả về 400", "Status 200" | `400`, `200` |
| 2 | `\bstatus[:\s]+(\d{3})\b` | "Status: 401" | `401` |
| 3 | `\b(HTTP\|status)[/_\s-]*(\d{3})\b` | "HTTP 422", "status_code 500" | `422`, `500` |

### 6.2 Map keyword → code (fallback)

Nếu Expected Result chỉ ghi text mô tả, không có status code:

| Keyword | → expected_code |
|---|---|
| "thành công", "success", "OK", "tạo thành công" | `200` hoặc `201` (xem context: GET → 200, POST → 201) |
| "không tìm thấy", "not found" | `404` |
| "không có quyền", "unauthorized", "chưa login" | `401` |
| "không được phép", "forbidden", "bị từ chối" | `403` |
| "lỗi validation", "sai định dạng", "invalid", "bad request" | `400` |
| "xung đột", "conflict", "duplicate", "trùng" | `409` |
| "lỗi server", "internal error" | `500` |
| "rate limit", "quá nhiều request" | `429` |

⚠️ Nếu cả 2 cách (regex + keyword) không match → flag warning, hỏi user.

---

## 7. Parse `expected_message` từ Expected Result

### 7.1 Pattern detection

| Pattern | Example | Output |
|---|---|---|
| `error[_ -]?code[:\s]+([A-Z_]+)` | "Error code: USERNAME_REQUIRED" | `USERNAME_REQUIRED` |
| `\b([A-Z][A-Z_]{3,})\b` (UPPER_SNAKE_CASE word) | "Trả về USERNAME_REQUIRED" | `USERNAME_REQUIRED` |
| `mã lỗi[:\s]+([A-Z_]+)` | "Mã lỗi: AMT_TOO_SMALL" | `AMT_TOO_SMALL` |
| Quoted message: `"([^"]+)"` (sau "message:" hoặc "error:") | `error: "Invalid input"` | `Invalid input` |

### 7.2 Khi không có error code

Expected Result chỉ ghi "Lỗi" hoặc "Fail" mà không nói cụ thể:
- → `expected_message` để trống
- Flag warning: "TC {id} không có error code cụ thể, anh nên bổ sung trong manual TC"

### 7.3 Conflict: Expected Result ghi nhiều error code

```
"Trả về 400 nếu USERNAME_REQUIRED hoặc 401 nếu INVALID_CREDENTIALS"
```

→ Ambiguous, không thể auto-decide. Flag warning, default lấy code đầu tiên.

---

## 8. Sentinel detection decision tree

```
Đọc Test Steps cell
    ↓
Có chứa "missing" / "không truyền" / "thiếu field"?
    YES → <MISSING>
    NO
    ↓
Có chứa "null" / "NULL"?
    YES → <NULL>
    NO
    ↓
Có chứa "empty" / "rỗng" / "trống" / `= ""`?
    YES → <EMPTY>
    NO
    ↓
Extract value literal (vd: `username = "abc"` → `abc`)
    Match được → giữ literal
    Không match → flag warning, để trống test_value
```

---

## 9. Multi-field TC — sentinel cho từng field

Khi tách multi-field TC (vd "thiếu username và password" → 2 row), apply detection RIÊNG cho từng field:

```
Original: "Thiếu username và password"
    ↓
Row 1: field_name=username, test_value=<MISSING>
Row 2: field_name=password, test_value=<MISSING>
```

```
Original: "Username rỗng và amount âm"
    ↓
Row 1: field_name=username, test_value=<EMPTY>
Row 2: field_name=amount, test_value=-100  (lấy từ "amount âm" → đoán giá trị tiêu biểu)
```

⚠️ Trường hợp "amount âm" — không có giá trị cụ thể → đoán `-1` hoặc `-100`. Flag warning để user review.
