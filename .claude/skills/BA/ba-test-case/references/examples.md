# Test Case Examples

## Example 1 — AC → Test cases mapping

### Source AC (from ba-user-story or ba-feature-spec)

> **AC-PAY-01 — Successful transfer to registered recipient** *(tests BR-01, BR-04)*
> - **Given** the recipient phone number is associated with an active verified wallet
> - **And** my balance is sufficient (amount + fee, if any)
> - **And** the amount is within my daily and per-transaction limit
> - **When** I confirm the transfer
> - **Then** the amount is deducted from my balance and credited to the recipient
> - **And** I receive an in-app notification and SMS confirmation with a reference number

### Derived test cases

**TC-PAY-001 — Transfer success, basic tier customer, well within limits**
- **Related AC:** AC-PAY-01
- **Related BR:** BR-01, BR-04
- **Priority:** P0 (critical path — most common transaction)
- **Preconditions:** Tester is a verified retail customer, tier=basic, balance=5,000,000 VND, daily-used=0 VND, tier daily-limit=50,000,000 VND
- **Test data:** Recipient = a separate verified retail customer with phone 0901234567
- **Steps:**
  1. Tester logs in
  2. Tester navigates to Transfer screen
  3. Tester enters recipient phone "0901234567"
  4. System displays recipient name (partially masked, e.g., "Nguyễn Văn A***")
  5. Tester confirms recipient name is correct
  6. Tester enters amount 100,000 VND
  7. Tester confirms the transfer
- **Expected result:**
  - Tester sees success screen with a transaction reference number
  - Tester's balance shows 4,900,000 VND
  - Tester receives in-app notification within 5 seconds
  - Tester receives SMS within 30 seconds (verify on tester's phone)
  - Recipient's app shows in-app notification
  - Recipient's balance shows previous + 100,000
  - Transaction appears in tester's history with timestamp and reference
- **Postconditions:**
  - System log records the successful transfer with reference, both customer codes, amount, timestamp
  - Both customers' transaction history shows the transfer
- **Notes:** Smoke test for happy path. Automation-friendly.

**TC-PAY-002 — Transfer at exactly per-transaction limit (boundary)**
- **Related AC:** AC-PAY-01 (boundary of "amount within per-transaction limit")
- **Related BR:** BR-04
- **Priority:** P1 (boundary case, less frequent but important)
- **Preconditions:** Verified retail customer, tier=basic, balance large enough, per-transaction limit = 20,000,000 VND
- **Test data:** Recipient = verified retail customer
- **Steps:** [as TC-001 but amount = 20,000,000 VND exactly]
- **Expected result:**
  - Transfer succeeds (limit is inclusive of exact value per BR-04)
  - All success observations as TC-001
- **Notes:** Tests boundary behavior — limit value should be allowed, not rejected

**TC-PAY-003 — Transfer 1 VND above per-transaction limit (boundary, negative)**
- **Related AC:** AC-PAY-01 (boundary, edge of limit)
- **Related BR:** BR-04
- **Priority:** P1
- **Preconditions:** Verified retail customer, per-transaction limit = 20,000,000 VND
- **Test data:** Recipient = verified retail customer
- **Steps:** [as TC-001 but amount = 20,000,001 VND]
- **Expected result:**
  - Transfer is rejected before funds move
  - Tester sees message identifying limit exceeded, showing the limit and the remaining daily limit
  - Tester's balance unchanged
  - No notification or SMS sent (verify)
  - No transaction in history
- **Postconditions:** No state changes; system log records rejection with reason
- **Notes:** Complements AC-PAY-03 (daily limit exceeded) — but tests *per-transaction* boundary

**TC-PAY-004 — Race condition: double-tap submit**
- **Related AC:** AC-PAY-01 + idempotency BR
- **Related BR:** BR-XX (idempotency at business level — no double-counted transactions)
- **Priority:** P0 (financial integrity)
- **Preconditions:** Verified retail customer with sufficient balance for one transfer
- **Test data:** Recipient = verified customer
- **Steps:**
  1. Tester logs in
  2. Tester sets up a transfer of 100,000 VND
  3. Tester rapidly taps the "Confirm" button twice within 500ms
- **Expected result:**
  - Only one transfer is executed (one debit, one credit)
  - Tester sees success screen for the single transaction
  - Tester's balance reflects exactly one 100,000 VND deduction
  - Recipient receives exactly one notification
- **Postconditions:** Exactly one transaction in system; both balances reflect one transfer
- **Notes:** This catches client-side or server-side idempotency bugs. Often missed in functional testing. Hard to automate reliably — may need to be manual or use specialized tool.

---

## Example 2 — Coverage matrix

| AC ID | AC Description (short) | Test case IDs | Coverage status |
|---|---|---|---|
| AC-PAY-01 | Successful transfer to registered recipient | TC-PAY-001, TC-PAY-002, TC-PAY-003, TC-PAY-004 | ✅ Covered (4 cases: happy + 2 boundaries + concurrency) |
| AC-PAY-02 | Insufficient balance | TC-PAY-005 | ✅ Covered (1 case) |
| AC-PAY-03 | Daily limit exceeded | TC-PAY-006, TC-PAY-007 | ✅ Covered (2 cases: at limit + over limit) |
| AC-PAY-04 | Suspicious activity step-up | TC-PAY-008 | ✅ Covered (1 case) |
| AC-PAY-05 | Recipient wallet locked | — | ⚠ NOT YET — needs TC |

Notes: AC-PAY-05 shows the value of the coverage matrix — it surfaces missing coverage immediately. The BA would add a test case before delivering, or note in self-check that it's an open gap.

---

## Example 3 — UAT scenario (V-B)

> ### UAT-001 — Mai mở tài khoản và chuyển tiền lần đầu cho bạn
>
> **Persona:** Mai, 28 tuổi, nhân viên văn phòng, mới chuyển sang dùng ví điện tử lần đầu, có sẵn 2 triệu VND trong tài khoản ngân hàng, có 1 người bạn cũng đang dùng ví.
>
> **Mục tiêu nghiệp vụ:** Mai tải app, đăng ký tài khoản, nạp tiền từ ngân hàng vào ví, rồi chuyển 200K cho bạn.
>
> **Trạng thái trước:** Mai chưa có tài khoản. Bạn của Mai đã có tài khoản verified.
>
> **Các bước:**
> 1. Mai tải app từ store và mở app
> 2. Mai thực hiện đăng ký bằng số điện thoại của mình (nhận OTP, xác minh)
> 3. Mai hoàn tất KYC (chụp CMND, selfie)
> 4. Mai chờ thông báo KYC thành công (trong UAT environment, KYC pass tự động sau 1 phút)
> 5. Mai liên kết tài khoản ngân hàng của mình với ví
> 6. Mai nạp 500K từ ngân hàng vào ví
> 7. Mai bắt đầu giao dịch chuyển tiền cho bạn (nhập SĐT bạn)
> 8. Mai xác nhận tên bạn hiển thị đúng
> 9. Mai chuyển 200K
> 10. Mai thấy thông báo thành công
> 11. Mai kiểm tra số dư còn lại
> 12. Mai liên hệ bạn để xác nhận bạn đã nhận tiền
>
> **Tiêu chí thành công:**
> - Mai hoàn tất toàn bộ flow trong ≤ 15 phút (không tính chờ KYC)
> - Mai không gặp lỗi blocking nào
> - Bạn của Mai nhận được tiền và thông báo
> - Mai có thể tìm được giao dịch trong lịch sử
> - Mai có thể giải thích cho người khác cách dùng app sau khi tự làm xong (tín hiệu UX rõ ràng)
>
> **Tín hiệu UAT thất bại (cần điều tra):**
> - Mai cần > 30 phút để hoàn tất (UX quá phức tạp)
> - Mai phải dừng và hỏi support (flow chưa self-explanatory)
> - Mai chuyển nhầm số hoặc nhầm amount (UX không đủ confirm steps)
> - Mai thấy tiền bị trừ nhưng bạn chưa nhận thông báo (race condition)

Notes:
- ✅ Persona realistic, has name and concrete context
- ✅ End-to-end across multiple features (registration, KYC, bank linking, top-up, transfer)
- ✅ Success criteria measurable (time, error count, business outcome)
- ✅ Failure signals beyond just bugs — includes UX issues that wouldn't fail functional test but matter for adoption
- ✅ At business workflow level, not technical step level

---

## Example 4 — Negative / edge cases (V-C)

Examples of categories often missed in V-A:

**Boundary — Empty input:**
> TC-NEG-001 — Submit transfer with empty amount
> - Preconditions: At Transfer screen
> - Steps: Leave amount field blank, tap Confirm
> - Expected: Form validation error shown; no API call made; user can fix and continue

**State — Locked recipient:**
> TC-NEG-002 — Transfer to recipient whose wallet is admin-locked
> - Preconditions: Recipient wallet exists but in `locked` state
> - Steps: Standard transfer flow to that phone number
> - Expected: Neutral message ("Cannot transfer to this account right now"); no detail about why (per AC-PAY-05); transfer not executed

**Concurrency — Two devices:**
> TC-NEG-003 — Same user initiates transfer on two devices simultaneously
> - Preconditions: Same user logged in on both phone and tablet, both at Transfer screen, balance just enough for one transfer
> - Steps: On phone: confirm 100K transfer. Immediately on tablet: confirm 100K transfer to different recipient
> - Expected: First transfer succeeds; second transfer is rejected due to insufficient balance (after first transfer's debit); or queued / blocked with clear message — define expectation per BR

**Timing — Maintenance window:**
> TC-NEG-004 — Transfer attempt during scheduled maintenance
> - Preconditions: System in maintenance mode (per BR-XX)
> - Steps: Attempt transfer
> - Expected: Friendly maintenance message; no error; user knows when service resumes; transfer can be retried later

**External dependency failure — SMS provider down:**
> TC-NEG-005 — Transfer succeeds but SMS to recipient fails
> - Preconditions: SMS provider simulated as failing
> - Steps: Complete a transfer
> - Expected: Transfer executes successfully (funds move); recipient gets in-app notification; SMS retry queued; user is informed that SMS may be delayed; no transaction rollback

Notes on V-C value: these tests probe robustness in ways V-A tests rarely do. Critical for financial / high-availability systems.
