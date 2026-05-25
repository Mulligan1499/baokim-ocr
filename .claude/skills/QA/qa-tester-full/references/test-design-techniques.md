# Test Design Techniques

6 kỹ thuật thiết kế test case bắt buộc nắm vững. Mỗi kỹ thuật có **tình huống dùng**, **cách áp dụng**, **ví dụ thực tế**, và **bẫy thường gặp**.

---

## 1. Equivalence Partitioning (EP) — Phân vùng tương đương

**Khi nào dùng**: input có thể chia thành các nhóm mà mọi giá trị trong cùng nhóm cho cùng một hành vi.

**Cách áp dụng**:
1. Xác định miền giá trị của input.
2. Chia thành các partition: ≥ 1 valid partition + ≥ 1 invalid partition.
3. Chọn 1 đại diện mỗi partition.

**Ví dụ**: Field "Tuổi" cho phép đăng ký từ 18 đến 65.
- Invalid: < 18 → đại diện: 10
- Valid: 18–65 → đại diện: 30
- Invalid: > 65 → đại diện: 70
- Invalid: không phải số → đại diện: "abc"
- Invalid: rỗng → đại diện: ""

→ 5 test case thay vì test hết 100+ giá trị.

**Bẫy thường gặp**:
- Quên invalid partition (chỉ test happy path).
- Không tách invalid thành nhiều loại (số âm, ký tự, rỗng, null, vượt độ dài) — gộp chung làm sót lỗi.

---

## 2. Boundary Value Analysis (BVA) — Phân tích giá trị biên

**Khi nào dùng**: luôn dùng kèm EP khi input có range hoặc length giới hạn.

**Cách áp dụng**: với mỗi biên, test 3 giá trị: **min-1, min, min+1** và **max-1, max, max+1**.

**Ví dụ**: Password 8–20 ký tự.
- 7 ký tự (min-1, invalid)
- 8 ký tự (min, valid)
- 9 ký tự (min+1, valid)
- 19 ký tự (max-1, valid)
- 20 ký tự (max, valid)
- 21 ký tự (max+1, invalid)

→ 6 test case.

**Lưu ý nâng cao**:
- Áp dụng cho cả **độ dài chuỗi**, **số lượng item trong list**, **ngày trong tháng**, **kích thước file upload**, **timeout**, **page size**.
- Với số thập phân, biên còn phải tính tới **độ chính xác** (precision): 99.99 vs 100.00 vs 100.01.
- Lỗi off-by-one (>= vs >) là loại bug BVA bắt được nhiều nhất — đừng bỏ qua.

---

## 3. Decision Table — Bảng quyết định

**Khi nào dùng**: business logic có nhiều điều kiện kết hợp với nhau (AND/OR), output phụ thuộc vào tổ hợp.

**Cách áp dụng**:
1. Liệt kê các điều kiện (Conditions) và hành động (Actions).
2. Vẽ bảng với mọi tổ hợp T/F của điều kiện (2^n cột).
3. Loại bỏ các tổ hợp không khả thi (impossible combinations).
4. Mỗi cột còn lại = 1 test case.

**Ví dụ**: Quy tắc giảm giá:
- Là member? (Y/N)
- Đơn hàng > 1.000.000? (Y/N)
- Có voucher? (Y/N)

| Condition         | TC1 | TC2 | TC3 | TC4 | TC5 | TC6 | TC7 | TC8 |
|-------------------|-----|-----|-----|-----|-----|-----|-----|-----|
| Member            | Y   | Y   | Y   | Y   | N   | N   | N   | N   |
| Đơn > 1M          | Y   | Y   | N   | N   | Y   | Y   | N   | N   |
| Có voucher        | Y   | N   | Y   | N   | Y   | N   | Y   | N   |
| **Discount %**    | 25  | 15  | 15  | 10  | 15  | 5   | 10  | 0   |

→ 8 test case bao phủ toàn bộ logic.

**Bẫy thường gặp**:
- Bỏ sót một điều kiện → bảng thiếu cột → bug logic lọt lưới.
- Khi có > 4 điều kiện (16+ tổ hợp), nên kết hợp với **Pairwise** để giảm số case.

---

## 4. State Transition Testing — Kiểm thử chuyển trạng thái

**Khi nào dùng**: đối tượng có vòng đời với nhiều trạng thái (Order, User account, Subscription, Document).

**Cách áp dụng**:
1. Vẽ state diagram: states + transitions + events triggering transitions.
2. Test các loại:
   - **Valid transition**: từ state A → B qua event đúng → đúng.
   - **Invalid transition**: từ state A → C qua event không hợp lệ → bị từ chối.
   - **Loop**: state quay về chính nó.
   - **Coverage**: 0-switch (mỗi transition 1 lần) hoặc 1-switch (cặp transition liên tiếp).

**Ví dụ**: Đơn hàng e-commerce.
States: Draft → Submitted → Paid → Shipped → Delivered → (Returned / Closed)

Test cases bắt buộc:
- Draft → Submitted (valid)
- Submitted → Paid (valid)
- Paid → Shipped (valid)
- **Submitted → Shipped trực tiếp** (invalid — phải qua Paid)
- **Delivered → Submitted** (invalid — không thể quay ngược)
- **Returned → Returned** (invalid — không return 2 lần)
- Cancel từ mỗi state (Draft, Submitted, Paid → cho phép; Shipped, Delivered → tùy policy).

**Bẫy thường gặp**:
- Chỉ test happy path (Draft → Delivered) mà bỏ qua các nhánh hủy/hoàn.
- Bỏ qua **timeout transitions** (vd: Draft sau 30 ngày tự Expired).

---

## 5. Pairwise Testing (All-pairs) — Kiểm thử cặp đôi

**Khi nào dùng**: nhiều tham số tổ hợp, không thể test full Cartesian (vd: 5 tham số × 4 giá trị = 1024 case là quá nhiều).

**Lý thuyết**: phần lớn defect được phát hiện khi test **mọi cặp** giá trị giữa 2 tham số bất kỳ — không cần test mọi tổ hợp 3+ chiều.

**Cách áp dụng**:
1. Liệt kê tham số và giá trị mỗi tham số.
2. Dùng tool sinh tổ hợp pairwise (PICT của Microsoft, allpairspy, Hexawise) hoặc orthogonal array.
3. Số case giảm còn ~ max(n_i × n_j) với n_i, n_j là 2 tham số có nhiều giá trị nhất.

**Ví dụ**: Test form đăng ký với:
- Browser: Chrome, Firefox, Safari, Edge (4)
- OS: Windows, macOS, Linux (3)
- Plan: Free, Pro, Enterprise (3)
- Payment: Card, PayPal, Bank (3)

Full combination: 4×3×3×3 = **108 case**.
Pairwise: ~**12 case** mà vẫn bao phủ mọi cặp đôi.

**Bẫy thường gặp**:
- Áp dụng cho business logic phức tạp — pairwise không thay thế được decision table khi có ràng buộc giữa các điều kiện.
- Quên đánh dấu **constraint** (vd: Plan=Free thì Payment=null) → tool sinh ra case không khả thi.

---

## 6. Error Guessing & Exploratory Testing

**Khi nào dùng**: bổ sung sau khi đã có test case formal — để bắt các bug mà kỹ thuật formal không bắt được.

**Error Guessing — danh mục các lỗi kinh điển cần "đoán":**

**Input field**:
- Rỗng, null, chỉ space
- Vượt max length (paste 10MB text)
- SQL injection: `' OR '1'='1`
- XSS: `<script>alert(1)</script>`
- Unicode: emoji 🎉, RTL Arabic, ký tự tổ hợp
- Leading/trailing space (đặc biệt cho email, username)
- Số: âm, 0, số rất lớn (overflow), số thập phân nhiều chữ số

**Date/Time**:
- 29/02 năm nhuận vs không nhuận
- Timezone (user GMT+7, server UTC, DB UTC)
- DST transition (giờ mùa hè/đông)
- Past date / future date trong field "ngày sinh"

**Concurrency**:
- 2 user cùng edit 1 record
- Double-click submit
- Nhanh tay back/forward browser khi đang submit

**Network**:
- Mất kết nối giữa chừng
- Timeout request
- Slow 3G

**Resource**:
- Disk full khi upload
- Memory leak khi mở/đóng nhiều lần
- Session expired giữa thao tác

**Exploratory Testing — Session-based**:
- Đặt **charter** (mục tiêu khám phá) trong 60–90 phút.
- Ghi note theo SBTM (Session-Based Test Management): Setup, Test design, Bug investigation, Coverage.
- Output: bug + observation + idea cho test case formal.

---

## Bảng quyết định: Chọn kỹ thuật nào?

| Đặc điểm requirement | Kỹ thuật bắt buộc | Kỹ thuật bổ sung |
|---|---|---|
| Field input có range | BVA | EP |
| Field input có nhóm hợp lệ/không hợp lệ | EP | BVA |
| Logic if/else nhiều tầng | Decision Table | Error Guessing |
| Object có lifecycle | State Transition | Error Guessing |
| Cấu hình môi trường nhiều chiều | Pairwise | EP |
| Search/filter/sort | EP + BVA | Error Guessing |
| Workflow phê duyệt | State Transition + Decision Table | — |
| Migration / data transform | EP cho từng kiểu data | Error Guessing |
| Sau khi đã có test case formal | — | Exploratory |

**Quy tắc**: với mỗi user story, **luôn áp dụng ít nhất 2 kỹ thuật** kết hợp. Một kỹ thuật đơn lẻ luôn để lọt bug.
