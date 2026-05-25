# Bug Report Template

> Mục tiêu: dev có thể **reproduce trong 2 phút** mà không cần hỏi lại. Bug report tệ là nguyên nhân lớn nhất khiến cycle test bị kéo dài.

---

## Trường bắt buộc

| Trường | Bắt buộc | Mô tả |
|---|---|---|
| **Bug ID** | tự sinh | Jira tự gen |
| **Title** | ✅ | Format: `[Module] Action — Symptom` (vd: `[Checkout] Apply 100% voucher — total = -1 thay vì 0`) |
| **Reporter** | ✅ | Tên QA |
| **Date Found** | ✅ | YYYY-MM-DD |
| **Environment** | ✅ | Env (DEV/QA/Stg/Prod) + URL + Build ID/Version |
| **Platform** | ✅ | Web (browser + version + OS) / Mobile (device + OS version + app version) / API (endpoint) |
| **Severity** | ✅ | S1 Critical / S2 High / S3 Medium / S4 Low |
| **Priority** | ✅ (PM xác nhận) | P1 / P2 / P3 / P4 |
| **Type** | ✅ | Functional / UI / Performance / Security / Data / Regression / Documentation |
| **Linked Test Case** | ✅ | TC_ID phát hiện ra bug |
| **Linked AC / Story** | ✅ | Jira story |
| **Pre-condition** | ✅ | Setup cần có |
| **Steps to Reproduce** | ✅ | Bước cụ thể, đánh số |
| **Expected Result** | ✅ | Theo AC / chuẩn |
| **Actual Result** | ✅ | Quan sát thực tế |
| **Reproducibility** | ✅ | Always / Often (>50%) / Sometimes / Once |
| **Evidence** | ✅ | Screenshot, video, HAR file, log, network response |
| **Workaround** | tùy chọn | Cách user tránh được tạm thời |
| **Suspected Cause** | tùy chọn | Gợi ý vùng code (nếu QA có hint) |

---

## Severity vs Priority — đừng nhầm lẫn

- **Severity**: ảnh hưởng kỹ thuật — QA quyết.
- **Priority**: thứ tự xử lý — PM/Tech Lead quyết.
- Có bug **S1-P3**: server crash khi user nhập 10 triệu ký tự — Critical về kỹ thuật, nhưng không ai làm vậy ngoài QA → priority thấp.
- Có bug **S3-P1**: typo trên homepage — không nghiêm trọng kỹ thuật, nhưng làm giảm trust → priority cao.

### Severity definition

| | Định nghĩa | Ví dụ |
|---|---|---|
| **S1 Critical** | App crash, data loss, security hole, blocker không workaround | App crash khi mở; mất data sau save; SQL injection lộ data; toàn bộ user không login được |
| **S2 High** | Chức năng chính không hoạt động, có workaround khó | Checkout fail với 1 phương thức thanh toán phổ biến; search trả sai kết quả |
| **S3 Medium** | Chức năng phụ sai, có workaround dễ | Sort sai cho 1 cột; tooltip không hiển thị; format ngày sai 1 chỗ |
| **S4 Low** | Cosmetic, typo, suggestion | Sai font 1 chữ; spacing không đều |

---

## Ví dụ Bug Report tốt

```
Title: [Checkout] Apply voucher 100% — total = -1 thay vì 0

Reporter: QA Nguyen
Date: 2024-11-15
Environment: QA — https://qa.shop.example.com — Build #2451
Platform: Web — Chrome 120.0.6099.110 — macOS 14.1
Severity: S2 High
Priority: P1 (PM confirmed)
Type: Functional - Data
Linked TC: TC_CHECKOUT_023
Linked Story: PROJ-789

Pre-condition:
- User đã login với account "qa.test@example.com"
- Cart có 1 sản phẩm "Sản phẩm A" giá 100.000 VND
- Voucher code "FREE100" tồn tại, type = percentage 100%, status active

Steps to Reproduce:
1. Vào trang Cart (/cart)
2. Click "Proceed to Checkout"
3. Tại bước Payment, nhập voucher code "FREE100" vào field "Voucher"
4. Click button "Apply"

Expected Result:
- Discount line hiển thị "-100.000 VND"
- Subtotal: 100.000 VND
- Total: 0 VND
- Voucher status hiển thị "Applied"

Actual Result:
- Discount line hiển thị "-100.001 VND"
- Subtotal: 100.000 VND
- Total: -1 VND  ← LỖI
- Voucher status hiển thị "Applied"
- API response /api/cart/apply-voucher trả total: -1

Reproducibility: Always (5/5 lần thử)

Evidence:
- screenshot1.png (UI hiển thị total -1)
- network.har (response API)
- console.log (không có error JS)

Suspected Cause:
- Logic tính discount có thể đang dùng `floor()` thay vì `min(discount, subtotal)`
- File suspect: services/checkout/discount.js dòng 45 (theo blame Git)

Workaround:
- Không có workaround cho user. Cần fix.

Notes:
- Test thêm với voucher 90% và 99% — ra kết quả đúng (10.000 VND, 1.000 VND)
- Chỉ xuất hiện với voucher 100%
- Test trên Firefox và Safari → cùng bug → bug ở backend, không phải frontend
```

---

## Ví dụ Bug Report tệ (đừng làm vậy)

```
Title: Voucher bị lỗi
Steps: Áp voucher thì total bị âm
Expected: Phải đúng
Actual: Sai
Severity: High
```

→ Dev sẽ phải hỏi lại 5 câu mới hiểu, kéo dài 1-2 ngày.

---

## Quy trình log bug

1. **Reproduce 2 lần** trước khi log (loại trừ flaky, environment issue).
2. **Tìm minimal reproduction**: bug chỉ xảy ra với một số input cụ thể? Tìm bộ input nhỏ nhất.
3. **Test trên build mới nhất** — tránh log bug đã fix.
4. **Search Jira** xem đã có bug tương tự chưa — nếu có, comment thêm vào, không tạo duplicate.
5. **Capture evidence trước khi log**:
   - Screenshot full-page (Chrome DevTools → Capture full size screenshot).
   - Video nếu có animation/timing — Loom, OBS.
   - HAR file (Network tab → Save all as HAR).
   - Console log (có error JS không?).
   - Server log (nếu có access) hoặc trace_id để dev tra.
   - Database state (nếu liên quan data).
6. **Log bug đầy đủ trường bắt buộc** trên Jira.
7. **Assign đúng team / dev owner** (theo module).
8. **Set Severity** theo định nghĩa, **đề xuất Priority**.
9. **Notify** trong Slack channel #project-bugs nếu Severity ≥ S2.

---

## Quy trình triage bug (QA + Dev + PM)

- **Cadence**: 2-3 lần/tuần, 30 phút.
- **Input**: list bug "New" hoặc "Open" chưa triage.
- **Quyết định cho mỗi bug**:
  - **Accept** + assign + set Priority + ETA fix.
  - **Reject** với lý do (Not a bug / By design / Duplicate / Cannot reproduce / Out of scope) — QA xác nhận hoặc phản biện.
  - **Defer** đến release sau (cần PM approve, ghi rõ ngày).
  - **Need more info** — QA bổ sung.

---

## Quy trình retest sau khi dev fix

1. Đọc commit message + change list để biết dev đã fix gì.
2. **Retest đúng bug**: lặp lại exact steps trong bug report.
3. **Regression test xung quanh**:
   - Cùng module: các flow gần kề có còn hoạt động.
   - Tích hợp: API liên quan có break.
   - Data: data đã có không bị corrupt sau khi fix.
4. **Verify root cause được fix**, không phải workaround che triệu chứng.
5. **Update bug status**:
   - **Verified / Closed** nếu pass.
   - **Reopen** với note rõ ràng nếu vẫn fail (đính kèm evidence mới).
6. Nếu bug **partially fixed** (fix 1 case, sót case khác) → mở bug mới link tới bug cũ thay vì để bug cũ open mãi.
