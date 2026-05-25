# Web Testing — Checklist & Strategy

Áp dụng cho ứng dụng web (SPA, MPA, dashboard, e-commerce, CMS…). Mục tiêu: bao phủ 5 nhóm test bắt buộc + 3 nhóm tùy ngữ cảnh.

---

## 1. Functional UI Testing (bắt buộc)

### 1.1 Form & Input
- Validation client-side: required, format (email, phone, URL), length min/max, regex.
- Validation server-side: phải reject ngay cả khi client validation bị bypass (test bằng Postman).
- Inline error message: hiển thị đúng vị trí, đúng nội dung, biến mất khi user fix.
- Submit button: disabled khi form invalid; spinner khi đang submit; không cho double-click.
- Auto-trim space đầu/cuối với username, email.
- Case-sensitivity: email phải case-insensitive khi login; password phải case-sensitive.
- Copy-paste vào field (đặc biệt OTP, credit card) — có cho phép không, có format lại không.
- Autofill browser: form vẫn hoạt động khi browser fill sẵn.

### 1.2 Navigation & Routing
- Direct URL access: paste URL vào tab mới phải vào đúng trang (không bị redirect về home).
- Browser back/forward: trạng thái form không mất, không trigger submit lại.
- Refresh page (F5) trong mọi trạng thái (đang loading, đã loading, có lỗi).
- Deep link với query param/hash → component hiển thị đúng state.
- 404 page khi URL không tồn tại; 401/403 khi không có quyền.
- Breadcrumb đồng nhất với URL.

### 1.3 Interactive Elements
- Modal: đóng bằng X, ESC, click overlay; focus trap đúng; không scroll background.
- Dropdown/Select: keyboard navigation (↑ ↓ Enter), search trong dropdown, scroll dropdown khi nhiều item.
- Date picker: giới hạn ngày min/max, locale, timezone display.
- File upload: drag & drop, multi-file, progress bar, cancel upload, validate type/size **trước khi upload** lên server.
- Drag & drop reorder: lưu thứ tự sau refresh.
- Inline edit: ESC để cancel, Enter để save, click outside để save/cancel theo design.

### 1.4 Data Display
- Empty state: trang khi chưa có data.
- Loading state: skeleton/spinner đúng vùng đang load.
- Error state: hiển thị thông báo, có nút retry.
- Pagination: page đầu, page cuối, page giữa, nhập số page không hợp lệ.
- Sort: mọi cột sortable, asc/desc, multi-sort nếu có.
- Filter: kết hợp nhiều filter, clear all, save filter, deep link filter qua URL.
- Search: empty result, special character, > max length, debounce delay.
- Infinite scroll: load đúng batch tiếp theo, không duplicate, scroll position khi back.

---

## 2. Cross-Browser & Cross-Device Compatibility (bắt buộc)

### Ma trận test tối thiểu (2024–2026):
| Browser | Version | OS | Ưu tiên |
|---|---|---|---|
| Chrome | Latest, Latest-1 | Windows 10/11, macOS | P0 |
| Safari | Latest | macOS, iOS | P0 |
| Firefox | Latest | Windows, macOS | P1 |
| Edge | Latest | Windows | P1 |
| Samsung Internet | Latest | Android | P2 (nếu có user Android nhiều) |

### Điểm khác biệt thường gặp (luôn check):
- **Safari**: date picker khác, `position: sticky` glitch, autoplay video bị chặn, IndexedDB private mode, Web Push hạn chế.
- **Firefox**: scroll behavior khác Chrome, `<input type=number>` khác.
- **Mobile browser**: viewport unit `vh` thay đổi khi address bar ẩn/hiện, touch event vs mouse event, hover state không có.
- Font rendering khác giữa OS — kiểm tra layout không vỡ.

---

## 3. Responsive & Layout Testing (bắt buộc)

### Breakpoint chuẩn cần test:
- 320px (iPhone SE) — smallest mobile
- 375px (iPhone 12/13) — common mobile
- 414px (iPhone Pro Max) — large mobile
- 768px (iPad portrait) — tablet
- 1024px (iPad landscape) — small desktop
- 1280px, 1440px, 1920px — desktop
- 2560px+ — large monitor (kiểm tra max-width)

### Checklist responsive:
- Không có horizontal scroll bất ngờ ở mobile.
- Text không bị cắt, không tràn container.
- Touch target ≥ 44×44px (Apple HIG) cho mobile.
- Image responsive: srcset, lazy loading, không vỡ aspect ratio.
- Modal/dropdown trên mobile: full-screen hoặc bottom-sheet, không bị che.
- Khi xoay device (portrait ↔ landscape): layout reflow đúng, không mất state.
- Zoom 200% (accessibility): không mất nội dung.

---

## 4. Accessibility (A11y) Testing (bắt buộc với public-facing)

Tuân thủ **WCAG 2.1 AA** tối thiểu. Test tự động + test thủ công.

### Tự động (cover 30–40%):
- axe DevTools, Lighthouse, WAVE.
- CI integration: pa11y-ci, axe-core trong Playwright/Cypress.

### Thủ công (cover 60–70%):
- **Keyboard navigation**: Tab qua mọi interactive element theo thứ tự logic; focus visible; ESC đóng modal/dropdown; Enter/Space activate button.
- **Screen reader**: NVDA (Windows), VoiceOver (macOS/iOS), TalkBack (Android) — đọc đúng label, đúng role, đúng state (checked, expanded, busy).
- **Color contrast**: text ≥ 4.5:1, large text ≥ 3:1, UI component ≥ 3:1.
- **Không dựa vào color alone**: error không chỉ là viền đỏ, phải có icon + text.
- **Form**: mọi input có `<label>`, error có `aria-describedby`, required có `aria-required`.
- **Heading hierarchy**: h1 → h2 → h3 không nhảy cóc.
- **Alt text**: ảnh có ý nghĩa → alt mô tả; ảnh decorative → alt="".
- **Reduced motion**: respect `prefers-reduced-motion` — tắt animation khi user setting.

---

## 5. Security Testing (bắt buộc)

### OWASP Top 10 — kiểm tra cơ bản:

- **Injection**:
  - SQL: `' OR '1'='1`, `'; DROP TABLE users; --` vào mọi input.
  - XSS: `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`, `javascript:alert(1)` vào URL field.
  - Command injection: `; ls`, `| whoami` cho field truyền vào shell.

- **Broken Authentication**:
  - Brute force login (lockout sau N lần).
  - Session token không expire khi logout.
  - Session fixation: token cũ vẫn dùng được sau login.
  - Password reset link reuse, không expire.
  - "Remember me" lưu plain credentials trong cookie.

- **Sensitive Data Exposure**:
  - Mật khẩu hiển thị trong response/log/URL.
  - Credit card hiển thị đầy đủ thay vì masked (****1234).
  - PII trong URL → bị log ở server, browser history, referer.
  - HTTPS không enforce (HTTP redirect không đầy đủ).

- **Broken Access Control**:
  - **IDOR** (Insecure Direct Object Reference): đổi `?id=123` thành `?id=124` → xem được data user khác.
  - **Vertical privilege escalation**: user thường gọi API admin.
  - **Horizontal privilege escalation**: user A xem/sửa data user B cùng role.
  - Force browsing: truy cập trực tiếp `/admin` mà không qua link.

- **Security Misconfiguration**:
  - HTTP headers thiếu: `Content-Security-Policy`, `X-Frame-Options`, `Strict-Transport-Security`, `X-Content-Type-Options`.
  - Cookie thiếu flag `Secure`, `HttpOnly`, `SameSite`.
  - Error message lộ stack trace, version, path.
  - Directory listing enable.
  - Debug mode bật trên production.

- **CSRF**: form sensitive (đổi password, transfer money) phải có CSRF token.

- **Clickjacking**: header `X-Frame-Options: DENY` hoặc CSP `frame-ancestors`.

---

## 6. Performance Testing (tùy ngữ cảnh)

Xem `references/non-functional-testing.md` cho chi tiết. Riêng web cần check:

- **Core Web Vitals**: LCP < 2.5s, INP < 200ms, CLS < 0.1.
- **Bundle size**: First Load JS < 200KB cho mobile.
- **Network throttle**: Slow 3G, Fast 3G — UX có còn dùng được không.
- **Memory leak**: mở/đóng modal 50 lần, navigate qua lại nhiều route — Chrome DevTools Memory profile.

---

## 7. SEO & Meta (cho site public)

- Title, meta description đúng cho từng page.
- Canonical URL.
- Open Graph + Twitter Card cho share social.
- Sitemap.xml, robots.txt.
- SSR/SSG nếu cần SEO (SPA pure không index tốt).
- 301 redirect cho URL cũ.

---

## 8. Localization & Internationalization (i18n) (nếu multi-language)

- Mọi string từ resource file, không hardcode.
- Layout không vỡ với tiếng Đức (từ dài), tiếng Nhật (ký tự rộng), tiếng Ả-rập (RTL).
- Date/time/number/currency format đúng locale.
- Pluralization đúng (1 item vs 2 items vs 5 items — tiếng Nga có rule phức tạp).
- Không trộn lẫn ngôn ngữ trong cùng 1 màn hình.

---

## Tools đề xuất cho Web testing

| Loại | Tool |
|---|---|
| E2E automation | Playwright (ưu tiên), Cypress, Selenium |
| Visual regression | Percy, Applitools, Chromatic |
| Cross-browser cloud | BrowserStack, Sauce Labs, LambdaTest |
| Accessibility | axe DevTools, WAVE, Lighthouse |
| Performance | Lighthouse, WebPageTest, Chrome DevTools |
| Security | OWASP ZAP, Burp Suite Community |
| API mocking | MSW (Mock Service Worker) |
