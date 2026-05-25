# Figma → Test Case Generation

Khi user upload **screenshot Figma, link Figma, hoặc mô tả UI** kèm yêu cầu generate test case, áp dụng quy trình sau.

---

## 1. Phân loại input

User có thể đưa Figma theo nhiều cách:

| Loại input | Cách đọc |
|---|---|
| **Screenshot/PNG/JPG của Figma frame** | Phân tích image trực tiếp (Claude vision) |
| **PDF export từ Figma** | Đọc PDF, mỗi page = 1 màn hình |
| **Link Figma** (figma.com/file/...) | Không truy cập được — yêu cầu user export ảnh |
| **Mô tả text** (component spec) | Đọc text, hỏi clarify nếu thiếu |

**Quy tắc**: nếu user chỉ gửi link Figma, **yêu cầu họ export PNG hoặc PDF** trước. Không bịa UI.

---

## 2. Quy trình phân tích Figma → test case (8 bước)

### Bước 1 — Xác định context của màn hình
Hỏi user (nếu chưa rõ):
- **Đây là màn hình gì?** (login, dashboard, form đăng ký, list view, detail view, settings…)
- **Platform**: Web / Mobile (iOS/Android) / Tablet?
- **User role nào** truy cập màn này? (guest, customer, admin)
- **Ngữ cảnh trong flow**: trước màn này là gì, sau màn này là gì?
- **Có business rules đặc biệt** nào không? (vd: "Chỉ user verified mới thấy nút X")

### Bước 2 — Liệt kê element trên màn (UI inventory)
Quét toàn bộ màn hình, ghi nhận **mọi element có thể tương tác hoặc hiển thị data**:

| Element type | Test point cần check |
|---|---|
| **Input field** | Label, placeholder, max length, validation rule, required, format (email/phone/number/date) |
| **Button** | Label, state (enable/disable/loading), action sau click |
| **Dropdown / Select** | Default value, options, search-in-dropdown, max selectable |
| **Checkbox / Radio** | Default state, mutually exclusive |
| **Toggle / Switch** | Default state, persistence |
| **Date / Time picker** | Min/max date, locale, format |
| **File upload** | Loại file accept, max size, multi-file |
| **Image / Avatar** | Placeholder khi rỗng, alt text |
| **Link / Tab / Breadcrumb** | Target, active state |
| **Modal / Dialog** | Trigger, dismiss (X, ESC, backdrop click), focus trap |
| **Toast / Snackbar / Banner** | Khi nào hiện, auto-dismiss |
| **Pagination / Infinite scroll** | Page size, navigation |
| **Sort / Filter** | Default, multi-criteria, clear |
| **Search** | Placeholder, debounce, empty result, special char |
| **Loading state** | Skeleton, spinner |
| **Empty state** | Hiển thị khi data rỗng |
| **Error state** | Inline error, banner error |

### Bước 3 — Suy luận business rules từ UI
Dấu hiệu trên Figma thường ngụ ý rule:
- Field có dấu `*` đỏ → **required**.
- Field placeholder "Email" hoặc icon ✉️ → format email.
- Field "Mật khẩu" + icon 👁️ → password visibility toggle.
- Button disable màu xám → có điều kiện enable, **hỏi user về điều kiện**.
- Có dòng "Chấp nhận điều khoản" + checkbox → **bắt buộc tick mới được submit**.
- Có ràng buộc giữa các field (vd "Ngày kết thúc" >= "Ngày bắt đầu") — UI thường không thể hiện rõ → **hỏi user**.

**Quy tắc vàng**: nếu rule không rõ ràng từ Figma, **hỏi user**, không bịa.

### Bước 4 — Phân nhóm test case (sections)
Chia test case thành các section logic:
- **Display / Render** — màn hiển thị đúng các element, đúng default state.
- **Input validation** — validation từng field.
- **Happy path** — flow chính thành công.
- **Negative / Error path** — flow lỗi, error message.
- **Edge case** — boundary, empty, special character.
- **Interactive behavior** — modal, tooltip, dropdown, animation.
- **Responsive** (Web) — breakpoint, layout reflow.
- **Accessibility** — keyboard, screen reader (nếu yêu cầu).
- **Cross-platform** (Mobile) — iOS vs Android, orientation, keyboard hiện.

### Bước 5 — Áp dụng kỹ thuật test design
Cho mỗi field/element:
- Range/length → **BVA** (xem `test-design-techniques.md`).
- Group input → **EP**.
- Logic phức tạp (vd disable button theo nhiều condition) → **Decision Table**.
- Lifecycle (modal open → loading → success → close) → **State Transition**.

### Bước 6 — Viết test case theo template Excel
Mỗi test case có 6 cột bắt buộc:
- **Test Item**: tên ngắn gọn, bắt đầu bằng động từ.
- **Pre-Condition**: setup cần có (đã login, đang ở màn nào, data nào tồn tại).
- **Step**: đánh số 1, 2, 3 — mỗi step 1 hành động.
- **Expected Output**: kết quả mong đợi cụ thể, có thể verify được trên UI.
- **Priority**: High / Medium / Low.

### Bước 7 — Đặt câu hỏi clarify trước khi finalize
Nếu phát hiện gap, **hỏi rõ trước khi generate**:
- "Trên Figma có button 'Save draft' — khi nào hiển thị, có giới hạn số draft không?"
- "Mật khẩu yêu cầu rule nào? (min length, ký tự đặc biệt…)"
- "Sau khi submit thành công, redirect đến đâu?"
- "Với device màn hình nhỏ < 375px, layout thế nào?"

### Bước 8 — Generate file Excel & cung cấp summary
Dùng script `scripts/generate_testcase_xlsx.py` để xuất file Excel theo template. Sau khi generate:
- Báo số lượng test case từng section.
- Liệt kê **assumption** đã dùng (nếu có).
- Đề xuất các **non-functional test case** bổ sung (performance, accessibility…).

---

## 3. Bộ test case mẫu cho từng loại màn

### 3.1 Form (Login, Register, Edit Profile…)

**Sections bắt buộc**:
1. **Display** — render đúng field, label, placeholder, default value.
2. **Input validation** — mỗi field 1 group test:
   - Required (rỗng → error)
   - Format (email, phone, URL invalid)
   - Length boundary (min, min-1, max, max+1)
   - Special character, Unicode, emoji
   - Whitespace (leading/trailing space)
   - Paste data quá dài, paste với newline
3. **Submit — Happy path** — fill đúng → success state.
4. **Submit — Negative** — server reject (duplicate, expired, network error).
5. **Inter-field rule** — vd password vs confirm password, ngày bắt đầu vs kết thúc.
6. **Loading & disable** — đang submit thì button disabled, không cho double-click.
7. **Cancel / Reset** — hủy form, có confirm "Bạn có chắc?" nếu form đã thay đổi.

### 3.2 List / Table view

1. **Display** — table hiển thị đúng cột, header sticky (nếu có).
2. **Empty state** — message khi không có data.
3. **Loading state** — skeleton/spinner.
4. **Pagination / Infinite scroll** — đầu, cuối, giữa, vượt range.
5. **Sort** — mỗi cột sortable, asc/desc, default sort.
6. **Filter** — single, multi, clear all, không có kết quả.
7. **Search** — empty, partial match, special char, debounce.
8. **Bulk action** — select all, deselect, action với 1/nhiều/0 row.
9. **Row action** — view, edit, delete (có confirm).
10. **Permission** — user không có quyền → action ẩn hoặc disabled.

### 3.3 Detail view

1. **Display** — render đúng data.
2. **Empty fields** — khi field optional rỗng → hiển thị "—" hoặc bỏ qua.
3. **Long content** — text dài → wrap đúng, không tràn.
4. **Edit mode** — toggle edit, save, cancel.
5. **Optimistic update** — UI update ngay, rollback nếu API fail.
6. **Permission** — view-only vs edit theo role.
7. **Linked data** — click navigate đúng đến entity liên quan.

### 3.4 Dashboard

1. **Display** — chart/widget render đúng, đủ data.
2. **Empty state** — khi user mới chưa có data.
3. **Filter date range** — preset (7d, 30d, custom), validate range.
4. **Refresh** — manual refresh, auto-refresh interval.
5. **Drill-down** — click chart → detail view.
6. **Export** — PDF, CSV, Excel.
7. **Real-time update** — nếu có WebSocket.

### 3.5 Modal / Popup

1. **Trigger** — click button → modal hiển thị.
2. **Dismiss** — X button, ESC key, click backdrop.
3. **Focus trap** — Tab không thoát modal.
4. **Body scroll** — background không scroll khi modal mở.
5. **Action button** — Save, Cancel hoạt động đúng.
6. **Unsaved changes** — modal đang edit → close → confirm "Hủy thay đổi?"
7. **Multi modal** — modal A mở modal B → đóng B → A vẫn hiển thị.

---

## 4. Test case cho Mobile UI (bổ sung Web)

Khi platform là Mobile, **luôn thêm các section sau** (tham khảo `platform-mobile.md`):

1. **Orientation** — portrait, landscape, layout reflow.
2. **Gesture** — swipe, pinch zoom, pull to refresh, swipe to delete.
3. **Keyboard show** — không che field, scroll đúng.
4. **Touch target** — ≥ 44pt (iOS) / 48dp (Android).
5. **Safe area** — content không bị notch / home indicator che.
6. **Hardware back** (Android) — back đúng thứ tự stack.
7. **Permission dialog** — khi feature cần quyền (camera, location, notification).
8. **Network state** — offline, slow 3G, wifi → 4G transition.
9. **App lifecycle** — background → foreground, resume state đúng.

---

## 5. Ví dụ end-to-end

**Input**: User upload screenshot màn Login với:
- Logo công ty trên cùng.
- Title "Đăng nhập"
- Field Email (placeholder "Email", có icon ✉️)
- Field Password (placeholder "Mật khẩu", icon 🔒, có nút 👁️ để hiện/ẩn)
- Checkbox "Ghi nhớ đăng nhập"
- Link "Quên mật khẩu?"
- Button "Đăng nhập" (chính, màu xanh)
- Text "Chưa có tài khoản? Đăng ký" với link "Đăng ký"

**Bước 1-3 (clarify với user)**:
> Tôi cần làm rõ vài điểm trước khi generate:
> 1. Quy tắc password (min length, ký tự đặc biệt, ...)?
> 2. Sau login thành công redirect đến đâu?
> 3. "Ghi nhớ đăng nhập" lưu trong bao lâu?
> 4. Có khóa account sau N lần fail không?

User trả lời → tiếp tục.

**Bước 4-6 — Sections sinh ra**:

| Section | Số TC ước lượng |
|---|---|
| Display login screen | 5 |
| Email field validation | 8 |
| Password field validation | 8 |
| Password visibility toggle | 3 |
| Remember me checkbox | 3 |
| Login — Happy path | 4 |
| Login — Negative | 6 |
| Account lockout | 3 |
| Forgot password link | 2 |
| Register link | 2 |
| Responsive (web) / Orientation (mobile) | 4 |
| Accessibility | 5 |
| **Total** | **~53 TC** |

**Bước 8 — Output**: file Excel `Login_TestCases.xlsx` với module_code = "LOGIN", sheet name = "Đăng nhập".

---

## 6. Anti-pattern khi generate từ Figma

| Anti-pattern | Lý do tệ | Sửa |
|---|---|---|
| Bịa AC mà Figma không thể hiện | Test case không khớp business | Hỏi user |
| Chỉ có happy path | Bỏ sót defect | Tỷ lệ negative ≥ 40% |
| Test case chỉ "verify hiển thị" | Quá nông | Mỗi field có ≥ 3 TC validation |
| Không phân biệt Web vs Mobile | Sót test platform-specific | Thêm section riêng theo platform |
| Copy y nguyên label làm test name | Khó hiểu mục đích | Bắt đầu bằng "Verify…", "Validate…" |
| Test case quá chung "Test login" | Không actionable | Cụ thể: "Login với password 7 ký tự (dưới min)" |
