# Annotated Use Case Examples

## Example 1 — Step granularity

**Bad (composite step):**
> 3. User submits the form, system validates inputs, and if valid sends OTP to phone

Problems: three actions combined; ambiguous about what's atomic and which extensions branch where.

**Better (atomic steps):**
> 3. Khách hàng gửi form đăng ký
> 4. Hệ thống kiểm tra tính hợp lệ của form
> 5. Hệ thống gửi mã OTP qua SMS đến số điện thoại đã nhập

Now extensions can attach precisely: `4a — Form không hợp lệ`, `5a — Gửi OTP thất bại`.

---

## Example 2 — Extension that goes nowhere

**Bad:**
> `4a — Email already exists`: System shows error message.

Problems: doesn't say what happens next. Does the use case end? Return to which step? Offer alternative?

**Better:**
> `4a — Email đã tồn tại`:
> 1. Hệ thống hiển thị thông báo "Email này đã đăng ký"
> 2. Hệ thống cung cấp 2 CTA: "Đăng nhập" hoặc "Quên mật khẩu"
> 3. Use case kết thúc (user chuyển sang use case khác)

Every extension ends with: terminal state, return-to-step, or jump-to-extension.

---

## Example 3 — Wrong level

**Symptom:**
> Use case: "Verify phone OTP"
> Main scenario: 1. User enters OTP. 2. System validates. 3. System grants access.

Problem: this is subfunction level (🐟). It's a piece of "Register account" or "Login", not a goal on its own. Walking away after "verifying OTP" without doing anything else makes no sense.

**Better:** keep OTP verification as steps 4-5 inside a user-goal use case "Đăng ký tài khoản" (☁ → 🪁 user-goal).

---

## Example 4 — Technical drift

**Bad:**
> 5. System POSTs verification request to KYC service at /api/v2/verify
> 6. KYC service returns 200 OK with verified=true
> 7. System updates user.verified_at timestamp in users table

Problems: API endpoints, HTTP codes, database tables. Pure dev territory.

**Better:**
> 5. Hệ thống gửi thông tin cá nhân đến nhà cung cấp KYC để xác thực
> 6. Hệ thống nhận kết quả xác thực từ KYC
> 7. Hệ thống đánh dấu tài khoản là đã xác thực

Business level — observable to non-technical reader.

---

## Example 5 — Forgotten minimal guarantee

**Bad postconditions:**
> Postconditions: User has an active account.

Problems: only describes success. What if scenario fails partway through?

**Better:**
> **Minimal guarantee** (regardless of outcome):
> - Audit log records the registration attempt with timestamp, phone number, and final status
> - No partial account is created (no orphan records if registration fails midway)
> - User's phone number is not locked permanently (can retry after rate-limit cooldown)
>
> **Success guarantee** (when scenario succeeds):
> - User has an active account
> - User receives welcome SMS with login instructions
> - User can immediately log in
> - User appears in admin dashboard

---

## Short complete use case example (V-A standard, Vietnamese)

> ### UC-001 — Đăng ký tài khoản qua số điện thoại
>
> | Trường | Giá trị |
> |---|---|
> | Variant | V-A — Standard use case |
> | Variant rationale | User yêu cầu 1 use case đầy đủ cho registration flow |
> | Level | 🪁 User goal |
> | Scope | Hệ thống ví điện tử |
> | Status | Draft |
>
> **Tác nhân chính:** Khách hàng cá nhân chưa có tài khoản (potential customer)
> **Tác nhân phụ:** Nhà cung cấp SMS, nhà cung cấp KYC
>
> **Bên liên quan và mối quan tâm:**
> - Khách hàng: muốn đăng ký nhanh, an toàn, không rườm rà
> - Bộ phận Compliance: cần đảm bảo KYC đầy đủ theo quy định
> - Bộ phận Fraud: cần phát hiện đăng ký giả mạo / bot
> - Bộ phận Marketing: muốn lấy được thông tin để cá nhân hóa
>
> **Điều kiện tiên quyết:**
> - Khách hàng có số điện thoại Việt Nam hoạt động được
> - Khách hàng đã tải app
> - Nhà cung cấp SMS và KYC đang hoạt động
>
> **Sự kiện kích hoạt:** Khách hàng nhấn "Đăng ký" trên màn hình đầu của app
>
> **Kịch bản thành công chính:**
> 1. Khách hàng nhập số điện thoại
> 2. Hệ thống kiểm tra số chưa được đăng ký
> 3. Hệ thống gửi mã OTP qua SMS
> 4. Khách hàng nhập mã OTP
> 5. Hệ thống xác thực mã OTP
> 6. Khách hàng nhập thông tin cá nhân (họ tên, ngày sinh, địa chỉ)
> 7. Khách hàng chụp ảnh CMND/CCCD 2 mặt và ảnh selfie
> 8. Hệ thống gửi thông tin đến nhà cung cấp KYC để xác thực
> 9. Hệ thống nhận kết quả KYC thành công
> 10. Khách hàng tạo mật khẩu và xác nhận đồng ý điều khoản
> 11. Hệ thống kích hoạt tài khoản và hiển thị màn hình chào mừng
>
> **Các luồng mở rộng:**
>
> `2a — Số điện thoại đã được đăng ký`:
> 1. Hệ thống thông báo "Số đã đăng ký"
> 2. Hệ thống đề xuất "Đăng nhập" hoặc "Quên mật khẩu"
> 3. Use case kết thúc
>
> `3a — Không gửi được OTP (SMS provider lỗi)`:
> 1. Hệ thống thông báo lỗi và đề xuất "Thử lại"
> 2. Nếu khách hàng chọn thử lại: quay về bước 3 (tối đa 3 lần)
> 3. Nếu đã 3 lần: use case kết thúc thất bại, hệ thống ghi log để Ops xử lý
>
> `5a — OTP sai`:
> 1. Hệ thống tăng counter lỗi
> 2. Nếu < 3 lần: quay lại bước 4
> 3. Nếu ≥ 3 lần: khóa OTP cho số này 15 phút, use case kết thúc với failure
>
> `8a — KYC không pass`:
> 1. Hệ thống nhận kết quả "Cần xét thủ công" hoặc "Từ chối"
> 2. Nếu "Cần xét thủ công": Hệ thống tạo task cho team Compliance, thông báo khách "Đang xét duyệt, 1-2 ngày"; use case kết thúc với trạng thái pending
> 3. Nếu "Từ chối": Hệ thống thông báo lý do (nếu cho phép) và CTA hỗ trợ; use case kết thúc với failure
>
> `11a — Khách không đồng ý điều khoản`:
> 1. Hệ thống không kích hoạt tài khoản
> 2. Hệ thống lưu thông tin pending trong 24h để khách có thể quay lại
> 3. Sau 24h: thông tin bị xóa
>
> **Yêu cầu đặc biệt:**
> - Toàn bộ flow nên hoàn tất trong < 5 phút từ phía khách (cảm nhận)
> - Mọi bước xác thực phải có audit log
> - Thông tin CMND/CCCD lưu mã hóa theo policy Compliance
> - SMS OTP có hiệu lực 5 phút
>
> **Tần suất / concurrency:** ~5,000-10,000 đăng ký mới/ngày. Một số điện thoại không thể chạy 2 phiên đăng ký song song (phiên thứ 2 báo "đang đăng ký, vui lòng hoàn tất phiên hiện tại").
>
> **Điều kiện sau khi thực thi:**
>
> *Minimal guarantee* (luôn đúng):
> - Audit log ghi nhận lần đăng ký với thời gian, SĐT, trạng thái cuối
> - Không có tài khoản dở dang nếu flow bị gián đoạn
> - Số điện thoại không bị khóa vĩnh viễn (có thể thử lại sau cooldown)
>
> *Success guarantee* (khi thành công):
> - Khách hàng có tài khoản active
> - Khách hàng nhận SMS chào mừng với hướng dẫn login
> - Khách hàng có thể đăng nhập ngay
> - Hồ sơ KYC được lưu trong hệ thống Compliance
>
> **Câu hỏi mở:**
> - Có cho phép đăng ký bằng số điện thoại nước ngoài không?
> - Trường hợp chứng minh thư đã sử dụng cho tài khoản khác — xử lý thế nào?
> - Có cần email backup không (gửi link khôi phục khi mất số điện thoại)?
>
> **Phụ lục: giả định cần xác nhận:** [bảng theo pattern chuẩn]

Notes on why this works as a use case:
- ✅ Level stated (🪁 user goal) and main scenario achieves the goal
- ✅ 11 steps (within 5-15 range)
- ✅ Each step is one atomic action with clear actor
- ✅ Multiple extensions cover validation failures, external dependency failures, user choices
- ✅ Every extension terminates (kết thúc / quay lại bước X)
- ✅ Postconditions split into minimal and success guarantees
- ✅ Special requirements at business level (no "PostgreSQL", no "JWT")
- ✅ Stakeholders go beyond primary actor — surfaces Compliance, Fraud, Marketing concerns
