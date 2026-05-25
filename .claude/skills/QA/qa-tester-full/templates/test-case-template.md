# Test Case Template

## Định dạng cột chuẩn

| Cột | Bắt buộc | Mô tả |
|---|---|---|
| **TC_ID** | ✅ | Mã test case unique. Convention: `TC_<MODULE>_<NUM>` (vd: `TC_LOGIN_001`) |
| **Title** | ✅ | Tên ngắn gọn, mô tả mục đích test (≤ 100 ký tự). Bắt đầu bằng động từ: "Verify…", "Validate…", "Check…" |
| **Module / Feature** | ✅ | Module thuộc về (vd: Login, Checkout) |
| **Linked AC / Story** | ✅ | Link tới Jira story / AC ID để traceability |
| **Type** | ✅ | Functional / Negative / Boundary / Edge / Security / Performance / Usability / Compatibility |
| **Priority** | ✅ | P1 (Critical), P2 (High), P3 (Medium), P4 (Low) |
| **Pre-condition** | ✅ | Điều kiện trước khi chạy. Liệt kê **trước** Step. Vd: "User đã có account active, đang ở trang Login" |
| **Test Data** | ✅ | Data dùng trong test (cụ thể, không placeholder). Vd: `email=test@example.com, pwd=Pass@123` |
| **Steps** | ✅ | Đánh số 1, 2, 3… Mỗi step là 1 hành động duy nhất. Dùng động từ chỉ thị: "Nhập…", "Click…", "Chọn…" |
| **Expected Result** | ✅ | Kết quả mong đợi cho **mỗi step quan trọng** hoặc tổng kết cuối. Cụ thể, có thể verify được. |
| **Actual Result** | (khi run) | Điền khi thực thi |
| **Status** | (khi run) | Pass / Fail / Blocked / Not Run / N.A |
| **Bug ID** | (khi fail) | Link Jira bug |
| **Tester** | (khi run) | Người thực thi |
| **Run Date** | (khi run) | YYYY-MM-DD |
| **Notes** | tùy chọn | Quan sát thêm, screenshot link |

---

## Quy tắc viết test case chuẩn

### 1. Mỗi test case test 1 thứ
❌ "Verify login với email đúng + password đúng VÀ verify error khi sai password"
✅ Tách 2 case: 1 cho positive, 1 cho negative.

### 2. Step phải atomic
❌ "Nhập email và password rồi click Login"
✅ Tách:
   1. Nhập email vào field Email
   2. Nhập password vào field Password
   3. Click button Login

### 3. Expected Result cụ thể, kiểm chứng được
❌ "Login thành công"
✅ "User được chuyển đến `/dashboard`, hiển thị tên user ở header, có cookie `session_id` set với HttpOnly flag"

### 4. Test data cụ thể
❌ "Nhập email hợp lệ"
✅ "Nhập email = `qa.test@example.com`"

### 5. Title bắt đầu bằng "Verify"/"Validate"/"Check"
- ✅ "Verify user can login with valid credentials"
- ❌ "Login với account đúng" (mơ hồ kết quả mong đợi)

### 6. Số lượng step hợp lý
- 1 test case ≤ 10 step. Nếu nhiều hơn → tách hoặc dùng pre-condition.

---

## Ví dụ minh họa (positive case)

```
TC_ID: TC_LOGIN_001
Title: Verify user can login successfully with valid credentials
Module: Authentication / Login
Linked AC: PROJ-123 (AC1)
Type: Functional - Positive
Priority: P1

Pre-condition:
- User account "qa.test@example.com" tồn tại, status Active, có vai trò Customer
- User đang ở trang /login, đã logout
- Browser: Chrome 120, viewport 1440x900

Test Data:
- email: qa.test@example.com
- password: Pass@1234

Steps:
1. Nhập "qa.test@example.com" vào field Email
2. Nhập "Pass@1234" vào field Password
3. Click button "Login"

Expected Result:
- Step 1-2: field hiển thị giá trị đã nhập, không có error message
- Step 3:
  + URL chuyển sang /dashboard trong vòng 2s
  + Header hiển thị "Hi, QA Test"
  + Cookie session_id được set với flag HttpOnly và Secure
  + Network tab: POST /api/login trả về 200 với { "token": "..." }
  + Audit log DB: bảng login_history có record mới với user_id, login_at, ip_address
```

---

## Ví dụ minh họa (negative case — BVA)

```
TC_ID: TC_LOGIN_005
Title: Verify error when password length below minimum (7 characters)
Module: Authentication / Login
Linked AC: PROJ-123 (AC3 — password ≥ 8 characters)
Type: Functional - Negative - Boundary
Priority: P2

Pre-condition:
- User đang ở trang /login

Test Data:
- email: qa.test@example.com
- password: Pas@123  (7 ký tự — min - 1)

Steps:
1. Nhập email vào field Email
2. Nhập password "Pas@123" vào field Password
3. Click button "Login"

Expected Result:
- Step 3:
  + Inline error dưới field Password: "Mật khẩu phải có ít nhất 8 ký tự"
  + Button Login disabled hoặc không trigger request
  + Không có request POST /api/login được gửi (verify trong Network tab)
  + URL không thay đổi
```

---

## Excel format đề xuất (khi xuất file)

Cột | Width
---|---
TC_ID | 18
Title | 50
Module | 20
Linked AC | 15
Type | 18
Priority | 10
Pre-condition | 40
Test Data | 30
Steps | 50
Expected Result | 50
Actual Result | 30
Status | 12
Bug ID | 15
Tester | 15
Run Date | 12
Notes | 30

→ Khi user yêu cầu export → gợi ý dùng skill `xlsx` để generate file Excel với conditional formatting (status Pass = xanh, Fail = đỏ).

---

## Anti-pattern cần tránh

| Anti-pattern | Tại sao tệ | Sửa |
|---|---|---|
| "Test the login feature" | Quá chung, không actionable | Tách thành nhiều case cụ thể |
| Step nói chung "Test all fields" | Không reproducible | Mỗi field 1 case |
| Expected: "Should work" | Không verify được | Specific outcome có thể đo |
| Pre-condition trống khi cần setup | Tester không biết bắt đầu thế nào | Liệt kê đầy đủ |
| Test data placeholder "abc123" cho mọi case | Không phản ánh logic | Data sát với business |
| 30 step trong 1 case | Khó debug khi fail step nào | Tách hoặc dùng pre-condition |
| Trộn UI step + DB verify trong cùng step | Rối | Tách thành step UI và step DB verify |
