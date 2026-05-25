# Requirement Document → Test Case Generation

Reference này hướng dẫn cách convert các loại **tài liệu BA** thành test case. Đây là input phổ biến nhất của QA — chiếm ~80% công việc thiết kế test case.

---

## 1. Phân loại tài liệu BA

Mỗi loại có cấu trúc khác nhau, cần workflow khác:

| Loại tài liệu | Đặc điểm | Trọng tâm parse |
|---|---|---|
| **BRD** (Business Requirement Document) | Cấp cao, viết cho stakeholder/business | Business goal, business rule, scope, success criteria |
| **SRS** (Software Requirement Specification) | Chi tiết kỹ thuật, viết cho dev/QA | Functional requirements (FR), Non-functional (NFR), constraints |
| **PRD** (Product Requirement Document) | Focus product/feature | User goal, feature spec, edge case |
| **User Story** | Format ngắn cho Agile | Story + AC (Acceptance Criteria) |
| **Use Case Specification** | Chi tiết tương tác user-system | Actor, precondition, main flow, alternative flow, exception |
| **Functional Spec** | 1 feature cụ thể | UI flow, business logic, validation rule |
| **Mô tả tự nhiên** (chat/email) | Không có cấu trúc | Tự suy luận thành format chuẩn |
| **Confluence/Notion page** | Mix nhiều thứ | Cần extract phần liên quan |
| **Slack/email thread** | Discussion fragment | Tổng hợp thành spec, hỏi clarify nhiều |

---

## 2. Quy trình tổng quát (7 bước)

```
[1. Đọc & phân loại] → [2. Trích xuất requirements] → [3. Phát hiện gap]
   ↓
[4. Hỏi clarify] → [5. Phân tích test condition] → [6. Áp dụng test design]
   ↓
[7. Generate file Excel theo module]
```

### Bước 1 — Đọc & phân loại tài liệu

Trước khi viết test, **xác định**:
- Loại tài liệu (BRD/SRS/User Story...)?
- Phạm vi (1 feature, 1 module, hay cả release)?
- Đối tượng đọc (business hay technical)?
- Trạng thái (draft, approved, frozen)?

→ Quyết định **độ chi tiết test case** cần viết. Document approved + frozen → viết test case formal đầy đủ. Draft → viết outline, đợi finalize.

### Bước 2 — Trích xuất requirements

Đọc từng phần, ghi lại theo format chuẩn:

```
[REQ-001] Title: User có thể đăng ký tài khoản
  Type: Functional
  Source: SRS section 3.2.1
  Priority: High
  
  Description: ...
  
  Inputs:
    - Email (required, format email, max 254 char)
    - Password (required, 8-32 char, có chữ hoa+số+ký tự đặc biệt)
    - Confirm password (required, match password)
    - Tên (required, max 100 char)
    - Số điện thoại (optional, format VN)
    - Tick điều khoản (required)
  
  Output / Result:
    - Success: tạo account, gửi email verify, redirect to /verify
    - Fail: error message, không tạo account
  
  Business rules:
    - BR-01: Email phải unique trong hệ thống
    - BR-02: Password không được chứa email/tên
    - BR-03: Sau 3 lần đăng ký fail từ cùng IP → CAPTCHA
  
  Acceptance Criteria:
    - AC1: Email đúng format → accept
    - AC2: ...
  
  Out of scope: SSO, social login (release sau)
  
  Dependencies: REQ-002 (Email service), REQ-005 (User table)
  
  Open questions: 
    - ? Số điện thoại có verify OTP không?
    - ? Email verify có expire không?
```

**Bóc tách ra phải có**: Inputs, Outputs, Business rules, AC, Out-of-scope, Dependencies, Open questions.

### Bước 3 — Phát hiện gap

Tài liệu BA hiếm khi đầy đủ. **Đọc và đánh dấu**:

| Loại gap | Ví dụ | Cách handle |
|---|---|---|
| **Thiếu AC** | "User có thể đăng ký" — không có rule cụ thể | Hỏi BA |
| **Mơ hồ** | "Password mạnh" — không định nghĩa | Hỏi BA |
| **Mâu thuẫn** | Section 3.1 nói "max 30 ký tự", section 5.2 nói "max 50" | Báo BA, không tự sửa |
| **Thiếu negative path** | Chỉ mô tả happy path | Tự suy luận + verify với BA |
| **Thiếu non-functional** | Không nói gì về performance/security | Hỏi hoặc dùng default project |
| **Thiếu error handling** | Không nói khi service down thì sao | Hỏi BA + dev |
| **Out-of-scope không rõ** | Không liệt kê | Xác nhận với BA |

### Bước 4 — Hỏi clarify trước khi viết

**Quy tắc**: viết câu hỏi cụ thể, không hỏi chung chung.

❌ "Cần thêm chi tiết về password"
✅ "Password rule: min 8 ký tự — đã có. Còn các yêu cầu sau cần làm rõ:
   1. Có yêu cầu chữ hoa không?
   2. Có yêu cầu số không?
   3. Có yêu cầu ký tự đặc biệt không?
   4. Có check password trong từ điển common password không?
   5. Khi đổi password, có check không trùng N password gần nhất?"

Gửi 1 lần tất cả câu hỏi để BA trả lời 1 lần. Đừng hỏi nhỏ giọt.

### Bước 5 — Phân tích test condition

Cho mỗi requirement, sinh test condition:

```
REQ-001 (Đăng ký tài khoản)
├── TC group 1: Display form đăng ký
├── TC group 2: Validation field Email
│   ├── TC: Empty → error "required"
│   ├── TC: Invalid format → error "invalid format"
│   ├── TC: Email đã tồn tại → error "duplicate"
│   ├── TC: Email max length 254 → accept
│   ├── TC: Email length 255 → error "too long"
│   ├── TC: Email viết hoa → case-insensitive
│   └── TC: Email có space đầu/cuối → auto-trim
├── TC group 3: Validation field Password
├── TC group 4: Validation Confirm Password
├── TC group 5: Validation field Tên
├── TC group 6: Validation field Phone
├── TC group 7: Cross-field rules (password vs confirm, password không chứa email/tên)
├── TC group 8: Business rule (CAPTCHA sau 3 lần fail)
├── TC group 9: Submit happy path
├── TC group 10: Submit negative path
├── TC group 11: Email verification flow
├── TC group 12: Edge cases (concurrent registration, network fail)
└── TC group 13: Security (SQL injection, XSS, CSRF, brute force)
```

### Bước 6 — Áp dụng test design techniques

Cho mỗi group, áp dụng kỹ thuật phù hợp (xem `test-design-techniques.md`):
- Field có range → BVA
- Field có nhóm → EP
- Logic kết hợp nhiều điều kiện → Decision Table
- Object có lifecycle → State Transition

### Bước 7 — Generate file Excel theo module

Mỗi requirement = 1 module = 1 sheet (hoặc gộp các REQ liên quan vào 1 sheet với multiple sections).

Đặt **module_code** theo convention:
- `USR_REG` cho đăng ký
- `USR_LOGIN` cho login
- `ORD_CREATE` cho tạo đơn
- `PAY_PROCESS` cho thanh toán

---

## 3. Workflow cho từng loại tài liệu

### 3.1 BRD (Business Requirement Document)

BRD viết cấp cao, ít chi tiết kỹ thuật. **Mục tiêu của BRD**: hiểu **tại sao** làm feature này, **đo lường thành công** thế nào.

**Bóc tách từ BRD**:
- Business goal → suy luận **critical path** cần test (test ưu tiên P1).
- Stakeholder → suy luận **role-based test** (admin, customer, manager).
- Success metric → suy luận **non-functional test** (vd "10k đăng ký/ngày" → performance test).
- Compliance requirement (PCI, GDPR, HIPAA) → suy luận **security/audit test**.

**Hành động**:
- BRD **một mình không đủ** để viết test case chi tiết — cần thêm SRS/User Story/Functional Spec.
- Nếu user chỉ gửi BRD: viết **test strategy** + **test plan outline** trước, không vội viết test case.

### 3.2 SRS (Software Requirement Specification)

SRS chi tiết kỹ thuật, đây là tài liệu **tốt nhất** để viết test case formal.

**Bóc tách từ SRS**:
- Mục **Functional Requirements** → trực tiếp thành test case.
- Mục **Non-functional Requirements** → test performance, security, usability.
- Mục **Constraints** → test environment, browser support.
- Mục **Use Cases / User Stories** → test full flow.
- Mục **Data Requirements** → test data integrity, encoding.
- Mục **Interface Requirements** → test API contract, UI mockup.

**Quy tắc**: mỗi FR có ID (vd FR-001) → cột "Linked Requirement" trong test case ghi đúng ID đó để traceability.

### 3.3 PRD (Product Requirement Document)

PRD focus vào product feature. Tương tự SRS nhưng:
- Có **user persona** → test theo persona.
- Có **user journey** → test E2E theo journey, không chỉ test isolated component.
- Có **wireframe/mockup** → kết hợp với reference `figma-to-testcase.md`.

### 3.4 User Story (Agile)

Format chuẩn:
```
As a [role]
I want [goal]
So that [benefit]

Acceptance Criteria:
- Given [context]
- When [action]
- Then [outcome]
```

**Bóc tách**:
- **Role** → test cho từng role + test cross-role permission.
- **Goal** → happy path test.
- **AC** → mỗi AC ≥ 1 test case (positive + boundary + negative).

**Quy tắc**:
- AC viết theo Given-When-Then → mỗi AC convert thành 1 test scenario.
- Story không có AC → **từ chối viết test case**, yêu cầu BA bổ sung AC trước.
- Story có AC nhưng vague → hỏi BA cụ thể hóa.

### 3.5 Use Case Specification

Format chuẩn:
```
Use Case ID: UC-001
Name: User Login
Actors: Customer
Preconditions: User đã có account
Main Flow:
  1. User mở /login
  2. User nhập email và password
  3. System validate credentials
  4. System tạo session
  5. System redirect to /dashboard
Alternative Flows:
  3a. Credentials sai → error message → quay về step 2
  3b. Account locked → error → kết thúc
Postconditions: User logged in, session active
Exceptions:
  - Network error tại step 3 → retry button
  - DB down → 503 error
```

**Bóc tách**:
- **Main flow** → 1 happy path test case.
- **Mỗi Alternative flow** → 1 negative test case.
- **Mỗi Exception** → 1 error handling test case.
- **Preconditions** → "Pre-Condition" trong test case.
- **Postconditions** → 1 phần của "Expected Output".

→ Use Case Spec là **dễ convert sang test case nhất** vì đã có cấu trúc tương tự.

### 3.6 Functional Spec (1 feature)

Thường có:
- UI mockup → đọc kèm `figma-to-testcase.md`.
- Validation rule cho từng field → BVA + EP.
- Business logic → Decision Table.
- Error scenarios → Negative test.

### 3.7 Mô tả tự nhiên (chat, email, gõ tay)

Đây là tình huống khó nhất — không có cấu trúc.

**Workflow**:
1. **Đọc, paraphrase lại** theo cấu trúc chuẩn (Inputs/Outputs/Rules/AC) và gửi user verify trước:
   > "Tôi hiểu yêu cầu là... [paraphrase]. Đúng chưa?"
2. Sau khi user xác nhận → bóc tách như SRS.
3. Hỏi clarify các điểm thiếu.

**Đừng**: nhảy thẳng vào viết test case từ mô tả mơ hồ. Sẽ sai và lãng phí.

### 3.8 Confluence/Notion/Wiki page (mix nhiều thứ)

Thường chứa: design discussion, mockup, technical spec, FAQ, history. Cần **extract phần liên quan**:

1. Hỏi user: "Phần nào trong page này là spec chính cần test?"
2. Hoặc: scan page tìm heading như "Acceptance Criteria", "Requirements", "Specification", "User Flow".
3. Bỏ qua phần "Discussion", "History", "FAQ" trừ khi user chỉ đích danh.

### 3.9 Slack/email thread

**Cảnh báo**: thread thảo luận thường có **quyết định bị change**. Đoạn đầu thread có thể đã obsolete.

**Workflow**:
1. Đọc từ cuối thread lên (decision mới nhất).
2. Tổng hợp thành spec format chuẩn.
3. **Bắt buộc** confirm với user trước khi viết test case: "Tôi tổng hợp từ thread như sau... đúng chưa?"

---

## 4. Cấu trúc sections cho test case từ document

Tùy loại feature, phân nhóm test case theo template chung:

### Pattern 1: Form/Input feature
```
Section 1: Display & Initial state
Section 2: Validation từng field
Section 3: Cross-field rules
Section 4: Submit — Happy path
Section 5: Submit — Negative path
Section 6: Business rules
Section 7: Edge cases & Error handling
Section 8: Security
Section 9: Non-functional (perf/A11y) [nếu yêu cầu]
```

### Pattern 2: Workflow/Process feature
```
Section 1: Initial state
Section 2: Each step in main flow (1 section/step)
Section 3: Alternative flows
Section 4: Exception handling
Section 5: Permission & Role-based
Section 6: Concurrency
Section 7: Audit & Logging
```

### Pattern 3: Calculation/Business logic feature
```
Section 1: Test from decision table (mỗi cột = 1 TC)
Section 2: Boundary values
Section 3: Edge cases (zero, negative, very large)
Section 4: Precision (decimal, rounding)
Section 5: Currency/Localization (nếu có)
Section 6: Audit trail
```

### Pattern 4: Report/Analytics feature
```
Section 1: Display/Layout
Section 2: Data accuracy (verify với raw data)
Section 3: Filter & Drill-down
Section 4: Date range & Timezone
Section 5: Export (CSV/Excel/PDF)
Section 6: Performance với data lớn
Section 7: Permission (ai thấy data nào)
```

### Pattern 5: Notification/Email feature
```
Section 1: Trigger (khi nào gửi)
Section 2: Recipient (gửi cho ai)
Section 3: Content (đúng template, đúng dynamic field)
Section 4: Localization
Section 5: Delivery (success, fail, retry)
Section 6: Anti-spam (frequency limit, opt-out)
Section 7: Tracking (open, click)
```

---

## 5. Ví dụ end-to-end

### Input của user

User paste vào chat:

> "Hệ thống cần có chức năng tạo voucher giảm giá. Admin có thể tạo voucher với:
> - Mã code (text, 6-20 ký tự, viết hoa, không trùng)
> - Loại: phần trăm (1-100%) hoặc cố định (1k-10tr VND)
> - Áp dụng cho: tất cả sản phẩm / 1 nhóm sản phẩm / 1 user / nhóm user
> - Số lần dùng tối đa: per voucher (1-10000) và per user (1-100)
> - Thời hạn: từ ngày — đến ngày (tối đa 1 năm)
> - Min order: voucher chỉ áp dụng cho đơn ≥ X VND
>
> Khi user dùng voucher:
> - Nhập code ở checkout
> - System check valid → apply discount
> - Nếu không valid → hiện lý do (expired, used up, không đủ min, etc.)"

### Bước 1-3: Phân loại + Trích xuất + Gap

**Loại**: mô tả tự nhiên (ad-hoc), feature mức trung bình.

**Gap phát hiện**:
1. Voucher mã code có cho phép ký tự đặc biệt không? (chỉ chữ-số? cho phép `-`, `_`?)
2. Voucher giảm % > 100% có cho phép không? (vd 110% — không hợp lý nhưng cần rule)
3. Voucher cố định: nếu giảm > order total → total âm hay = 0?
4. Có thể stack nhiều voucher không?
5. Voucher gắn user — user có thể chia sẻ với người khác không?
6. Khi admin xóa voucher đang trong giỏ user → behavior?
7. Time zone của "từ ngày — đến ngày"?
8. "Số lần dùng per user" reset theo period nào?
9. Có audit log ai tạo/sửa/dùng voucher không?
10. Multi-language cho error message?

### Bước 4: Hỏi clarify

Gửi user 10 câu hỏi trên cùng 1 lần. Sau khi user trả lời (giả sử đầy đủ), tiếp tục.

### Bước 5-6: Phân tích test condition

Tách thành **2 module**:

**Module 1: Admin tạo/quản lý voucher** (`VOUCHER_MGMT`)
- Section 1: Display form tạo voucher
- Section 2: Validation field Code
- Section 3: Validation field Loại + giá trị giảm
- Section 4: Validation field Áp dụng cho
- Section 5: Validation field Số lần dùng
- Section 6: Validation field Thời hạn (ngày)
- Section 7: Validation field Min order
- Section 8: Cross-field rules (tổ hợp các condition)
- Section 9: Submit happy path
- Section 10: Submit negative
- Section 11: Edit voucher (đặc biệt edit khi đã có user dùng)
- Section 12: Delete voucher
- Section 13: List/search voucher
- Section 14: Permission (chỉ admin)

**Module 2: User áp dụng voucher** (`VOUCHER_APPLY`)
- Section 1: Display voucher input ở checkout
- Section 2: Apply voucher hợp lệ — happy path
- Section 3: Apply voucher invalid (expired, used up, không đủ min, sai code, không đúng nhóm sản phẩm/user)
- Section 4: Voucher giảm % vs cố định — calculation đúng
- Section 5: Voucher giảm > order total
- Section 6: Stack multiple voucher (nếu cho phép)
- Section 7: Remove voucher khỏi cart
- Section 8: Concurrency (2 user cùng dùng voucher cuối)
- Section 9: Race với admin (admin disable khi user đang checkout)

→ Tổng ~70-100 test case across 2 modules.

### Bước 7: Generate

```python
create_testcase_workbook(
    output_path="output/Voucher_Mgmt_TestCases.xlsx",
    module_name="Quản lý voucher",
    module_code="VCH_MGMT",
    creator="QA Team",
    sections=sections_module1,
)

create_testcase_workbook(
    output_path="output/Voucher_Apply_TestCases.xlsx",
    module_name="Áp dụng voucher",
    module_code="VCH_APPLY",
    creator="QA Team",
    sections=sections_module2,
)
```

→ 2 file Excel, mỗi file 1 module.

---

## 6. Anti-pattern khi convert document → test case

| Anti-pattern | Lý do tệ | Sửa |
|---|---|---|
| Đọc document, viết test case ngay | Bỏ sót gap, viết sai | Phải có bước parse + clarify |
| Copy y nguyên câu trong document làm test case | Không actionable | Convert thành format Verify/Validate có Step + Expected |
| Mỗi AC = 1 test case duy nhất | AC nào cũng có nhiều case (positive/negative/boundary) | Mỗi AC ≥ 3 test case |
| Bỏ qua phần "Out of scope" | Sau release user complain "không có chức năng X" | Test "không có chức năng X" cũng phải có (verify deferred features không xuất hiện) |
| Không hỏi BA, tự đoán rule | Sai requirement | Luôn hỏi clarify |
| Mỗi document → 1 file Excel khổng lồ | Khó quản lý, khó assign | Tách theo module logic |
| Không link test case về document | Mất traceability | Cột "Linked Req" hoặc tag trong Note |
| Bỏ qua document có vẻ đã obsolete | Document obsolete vẫn có thể chứa rule cũ vẫn áp dụng | Confirm với BA, không tự assume |
| Đếm số trang document để báo "feature lớn" | Sai metric | Đếm số FR/AC → đó mới là kích thước thực |
| Generate test case từ thread Slack mà không tổng hợp | Decision có thể đã change | Luôn paraphrase + confirm trước |

---

## 7. Checklist trước khi finalize

Trước khi xuất file Excel từ tài liệu BA:

- [ ] Đã đọc toàn bộ tài liệu (không skip section nào).
- [ ] Đã liệt kê đủ FR/AC theo ID.
- [ ] Đã phát hiện và hỏi BA về mọi gap.
- [ ] BA đã trả lời mọi câu hỏi (hoặc user xác nhận để generate với `[ASSUMPTION]`).
- [ ] Mỗi FR/AC mapping đến ≥ 1 test case (forward traceability).
- [ ] Test case có mix positive/negative/boundary (không 100% positive).
- [ ] Test case cho permission/role nếu feature liên quan auth.
- [ ] Test case cho non-functional nếu document yêu cầu.
- [ ] Module phân tách hợp lý, không quá to (1 sheet > 100 TC nên tách).
- [ ] Module code đặt theo convention nhất quán.
- [ ] File generate đã pass recalc (0 lỗi formula).
