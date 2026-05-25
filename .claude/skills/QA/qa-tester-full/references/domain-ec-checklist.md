# Domain Edge Case Checklist

Reference này liệt kê **edge case theo domain feature** mà AC từ BA thường thiếu hoặc viết quá tổng quát. Đọc reference này khi feature thuộc 1 trong 4 domain dưới đây — soi qua checklist, bổ sung TC trước khi finalize.

**Cách dùng:** sau khi gen test case xong, nếu feature thuộc domain nào → mở section tương ứng → đối chiếu từng EC xem đã có TC chưa → bổ sung những EC còn thiếu hoặc log GAP nếu AC chưa rõ.

> Không phải mọi EC trong reference đều cần test cho mọi feature — chọn theo rủi ro và scope. Mục tiêu: KHÔNG MISS vì quên nghĩ đến, không phải test 100%.

---

## 1. Domain: AI / OCR / Computer Vision

Feature có sử dụng AI để extract / classify / recognize từ ảnh hoặc PDF (OCR, face detection, document classification, ...). AC từ BA thường viết "ảnh không rõ" hoặc "ảnh bị che" — quá tổng quát, miss các root cause cụ thể.

### 1.1 Input quality (lý do AI fail mà BA hay quên)

| # | Edge case | Root cause | Expected behavior |
|---|---|---|---|
| 1 | Ảnh **mờ toàn ảnh** (out of focus) | Tay run, focus sai | Mọi field confidence thấp, requires_review=true, quality="low" |
| 2 | Ảnh **mờ cục bộ** (1 field) | Motion blur 1 vùng | Field đó flagged=true, các field khác bình thường |
| 3 | Ảnh **lóa sáng / glare** | Flash, đèn neon phản xạ giấy | Vùng lóa → value="", flagged=true, KHÔNG bịa |
| 4 | Ảnh **ngược sáng / backlit** | Chụp gần cửa sổ, toàn ảnh tối | quality="low", overall_confidence thấp |
| 5 | Ảnh **thiếu sáng / chụp đêm** | Không đủ ánh sáng | Tương tự #4 |
| 6 | Ảnh **quá sáng / overexposed** | Flash quá gần, white-out | Tương tự #4 |
| 7 | Ảnh **nghiêng 15-45°** | Chụp lệch | Auto-rotate hoặc warning ORIENTATION |
| 8 | Ảnh **xoay 90°** (landscape mode) | Cầm điện thoại ngang | Auto-rotate hoặc reject |
| 9 | Ảnh **lộn ngược 180°** | Chụp ngược | Tương tự #8 |
| 10 | Ảnh **resolution thấp** (vd 200×300px) | Crop quá mức, screenshot màn lock | quality="low", confidence thấp |
| 11 | Ảnh **nén JPEG mạnh** (quality 10%) | Resize qua Zalo/Messenger | quality="low" |
| 12 | Ảnh có **bóng giấy / nếp gấp** | Giấy không phẳng | Field bị bóng → confidence thấp |
| 13 | Ảnh **chụp qua màn hình** (moire pattern) | Chụp lại CCCD trên màn iPad | quality="low" hoặc reject |
| 14 | Ảnh **có watermark đè** lên field | Stamped "COPY", "DRAFT" | Field bị che → value="" hoặc flagged |
| 15 | Ảnh **có chữ ký viết tay đè** lên field | Người ký lấn vào ô data | Tương tự #14 |
| 16 | Ảnh **scan đen trắng độ tương phản thấp** | Máy scan cũ | quality="low" |

### 1.2 Content quality

| # | Edge case | Expected |
|---|---|---|
| 17 | Ảnh **đúng loại giấy tờ nhưng hết hạn** (vd CCCD hết hạn 2020) | Vẫn extract, KHÔNG tự reject vì hết hạn (đó là biz logic, không phải OCR) |
| 18 | Ảnh **giấy tờ bị tẩy xóa / sửa chữa thủ công** | Field bị sửa → confidence thấp, flagged |
| 19 | Ảnh **chỉ chứa 1 nửa giấy tờ** (cắt mất nửa dưới) | Field bị mất → value="", warning="missing_field" |
| 20 | Ảnh **2 giấy tờ trong cùng 1 ảnh** | Verify chỉ extract 1 (file đầu hoặc lớn nhất) hoặc reject |
| 21 | Ảnh **giấy tờ giả/fake** (in nhái) | Nếu spec có anti-fraud → flag; nếu không → vẫn extract bình thường |
| 22 | Ảnh **photocopy đen trắng giấy tờ màu** | Vẫn extract được nhưng confidence có thể thấp hơn ảnh gốc màu |

### 1.3 AI hallucination prevention

| # | Edge case | Expected |
|---|---|---|
| 23 | Field bị **che hoàn toàn** | value="", confidence=0, KHÔNG bịa số |
| 24 | Field bị **che 1 phần** (vd số CCCD bị che 3 chữ cuối) | Verify AI có bịa nốt phần che không. Expected: trả phần thấy được + flag |
| 25 | Field **để trống trên giấy tờ** (vd ngày hết hạn trống) | value="", flagged=false (vì AC: trống thật thì khác bị che) |
| 26 | Field **viết tay không rõ chữ** | Confidence thấp, flagged |
| 27 | Field có **ký tự đặc biệt / dấu** (vd tên VN có dấu) | Verify extract đúng dấu, không mất dấu |
| 28 | Field **chữ Trung viết liền** không khoảng trắng | Verify phân từ đúng |
| 29 | Field **số có dấu chấm/phẩy** (vd 1.000.000) | Verify giữ format hay parse số |
| 30 | Tài liệu **không có loại nào trong taxonomy** (vd thiệp cưới) | document_type="unknown", overall_confidence=0 |

### 1.4 Multi-language & translation

| # | Edge case | Expected |
|---|---|---|
| 31 | Tài liệu **song ngữ tỷ lệ 50-50** | language_detected="mixed", warning |
| 32 | Tài liệu **3 ngôn ngữ trộn** | language_detected="mixed" hoặc ngôn ngữ chính, warning |
| 33 | Tài liệu **ngôn ngữ ngoài scope** (vd Pháp, Nhật, Hàn) | language_detected="other", translation_vi=null, raw_text best-effort |
| 34 | Tên người **không thuộc ngôn ngữ chính** (vd hợp đồng VN có ký tên người Mỹ) | Verify không cố Hán-Việt-hóa tên Mỹ |
| 35 | Tài liệu **toàn số/code, không có chữ** (vd sao kê chỉ số) | Vẫn extract, language_detected có thể là "other" hoặc "unknown" |
| 36 | Tài liệu có **emoji / icon** trộn vào | Verify không crash, ignore hoặc giữ nguyên |

### 1.5 Versioning & model drift

| # | Edge case | Expected |
|---|---|---|
| 37 | Đổi AI model version giữa môi trường staging vs prod | ai_model_version trả về khác nhau, audit log ghi đúng version |
| 38 | Re-run cùng file sau khi đổi model | Verify cache strategy: invalidate khi đổi model hay giữ cache cũ |

---

## 2. Domain: Payment / Financial

Feature liên quan đến thanh toán, ví, chuyển khoản, refund, settlement. AC thường viết happy path đầy đủ nhưng miss race condition, double-spend, currency edge case.

### 2.1 Money math & precision

| # | Edge case | Expected |
|---|---|---|
| 1 | Số tiền **= 0** | Reject hoặc accept tùy biz (vd cho phép authorize $0 để verify thẻ) |
| 2 | Số tiền **âm** | Reject 422, không tự "đảo dấu" |
| 3 | Số tiền **thập phân nhiều chữ số** (vd 100.123456) | Verify rounding rule (HALF_UP, HALF_EVEN), không lưu thiếu/thừa số lẻ |
| 4 | Số tiền **overflow** (vd 99999999999999) | Reject 422, không crash, không wrap-around âm |
| 5 | Số tiền **dạng scientific notation** ("1e10") | Reject 422 |
| 6 | Currency **mismatch** (charge USD nhưng wallet VND) | Reject 422 hoặc auto-convert (verify FX rate source + lock time) |
| 7 | Currency **không support** (vd ZWL Zimbabwe) | Reject 422 |
| 8 | **Rounding loss** (1/3 USD chia 3 người) | Verify total đúng, không mất xu cuối |

### 2.2 Race condition & idempotency

| # | Edge case | Expected |
|---|---|---|
| 9 | **Double-spend**: 2 request charge cùng wallet ID đồng thời, balance đủ cho 1 | Chỉ 1 thành công, 1 fail với 409/422, balance không âm |
| 10 | **Refund-race**: refund + new charge cùng lúc | Sequence đúng, balance reflect đúng |
| 11 | **Idempotency key trùng**, body khác | Trả response cũ (idempotent), KHÔNG process body mới |
| 12 | **Idempotency key trùng**, body giống y hệt | Trả response cũ |
| 13 | **Idempotency key trùng** trong window khác nhau (vd 24h vs 7 ngày) | Verify window đúng spec |
| 14 | **Retry sau timeout**: client retry vì timeout, server thực ra đã process | Idempotency bảo vệ — không charge 2 lần |
| 15 | **Webhook fire 2 lần** cùng event_id | Consumer xử lý idempotent — chỉ apply 1 lần |

### 2.3 Settlement & reconciliation

| # | Edge case | Expected |
|---|---|---|
| 16 | **Daily cut-off boundary**: transaction tại 23:59:59.999 vs 00:00:00.001 | Verify thuộc settlement date nào (theo TZ rule) |
| 17 | **Timezone shift**: server UTC, merchant Asia/HCM | Settlement period đúng theo TZ merchant |
| 18 | **DST transition** (nếu region có DST) | Không miss/duplicate 1 giờ |
| 19 | **Settlement amount mismatch**: gross - fee ≠ net | Reject batch, alert ops |
| 20 | **Currency conversion** trong settlement (charge USD, settle VND) | FX rate dùng tại thời điểm nào, lock vào audit |

### 2.4 Refund & chargeback

| # | Edge case | Expected |
|---|---|---|
| 21 | Refund **lớn hơn original amount** | Reject 422 |
| 22 | Refund **nhiều lần partial** vượt original khi cộng dồn | Reject sau khi total > original |
| 23 | Refund **transaction đã refund full** | Reject 409 |
| 24 | Refund **transaction trạng thái pending** (chưa capture) | Verify behavior: cancel thay vì refund |
| 25 | Chargeback **transaction đã refund** | Verify dispute logic |
| 26 | Refund **sau khi merchant đã settle** | Verify clawback / negative balance handling |

### 2.5 Compliance & limits

| # | Edge case | Expected |
|---|---|---|
| 27 | Transaction vượt **per-transaction limit** | Reject 422 với mã code rõ |
| 28 | Transaction vượt **daily/monthly limit** (cộng dồn) | Reject 422 |
| 29 | Transaction từ **blacklisted card/account** | Reject với code AML |
| 30 | **KYC chưa đủ tier** cho amount | Reject 422 với hint upgrade KYC |
| 31 | **3DS challenge fail** | Reject với code đúng (không leak chi tiết) |

---

## 3. Domain: Authentication / Authorization

AC từ BA thường viết "login với credentials đúng → success" nhưng miss session, token, timing, CSRF.

### 3.1 Credential validation

| # | Edge case | Expected |
|---|---|---|
| 1 | Password **= rỗng** | Reject 401, không bypass |
| 2 | Username **case sensitivity** ("Admin" vs "admin") | Verify policy nhất quán |
| 3 | Username chứa **whitespace** ("user " vs "user") | Trim hoặc reject — verify policy |
| 4 | Password chứa **null byte** | Reject hoặc strip, không truncate auth bypass |
| 5 | Password **đúng prefix** ("password" vs "password123") | Compare full string, không prefix match |
| 6 | Username chứa **Unicode normalization** (é vs é khác codepoint) | Normalize trước compare |

### 3.2 Session & token

| # | Edge case | Expected |
|---|---|---|
| 7 | Token **expired** | Reject 401 với code rõ |
| 8 | Token **chưa active** (nbf future) | Reject 401 |
| 9 | Token **revoked** | Reject 401 (verify revocation list hoạt động) |
| 10 | Token **signature sai** | Reject 401 |
| 11 | Token **alg=none** (JWT bypass) | Reject 401 |
| 12 | Token **alg substitution** (RS256 → HS256 với public key làm secret) | Reject 401 |
| 13 | Token với **kid manipulation** (path traversal) | Reject 401 |
| 14 | **Session fixation**: login với session ID do attacker cung cấp | Server cấp session ID mới sau login |
| 15 | **Concurrent session**: cùng user login 2 thiết bị | Verify policy (allow / kick old / limit) |
| 16 | **Session timeout** giữa idle | Verify idle timeout đúng spec |
| 17 | **Logout 1 session** không kill các session khác | Verify scope logout |
| 18 | **Token refresh** sau khi user đổi password | Refresh token cũ bị revoke |

### 3.3 Brute force & timing

| # | Edge case | Expected |
|---|---|---|
| 19 | **N lần fail liên tiếp** (vd 5) → lockout | Lock đúng số lần, message không leak |
| 20 | **Lockout duration** | Verify duration đúng spec |
| 21 | **Distributed brute force** (1 password thử nhiều username) | Verify rate limit theo IP |
| 22 | **Timing attack** username enumeration | Response time username đúng/sai phải giống nhau (constant time compare) |
| 23 | **Error message** không leak username tồn tại hay không | "Invalid credentials" cho cả 2 trường hợp |
| 24 | **Password reset enumeration** | Response giống nhau cho email tồn tại / không tồn tại |

### 3.4 Authorization & RBAC

| # | Edge case | Expected |
|---|---|---|
| 25 | **IDOR** — user A access resource của user B | Reject 403/404 |
| 26 | **Vertical privilege escalation** — user thường gọi admin endpoint | Reject 403 |
| 27 | **Horizontal privilege escalation** — đổi role mid-session | Verify không có endpoint cho phép tự đổi role |
| 28 | **Permission cache stale** — admin revoke quyền nhưng user vẫn dùng được vài phút | Verify cache TTL hoặc revoke tức thời |
| 29 | **Multi-tenant cross-access** | Reject 403 cho cross-tenant |

### 3.5 Multi-factor

| # | Edge case | Expected |
|---|---|---|
| 30 | OTP **đã dùng** | Reject — single use |
| 31 | OTP **expired** | Reject |
| 32 | OTP **brute force** (thử 1000 mã 6 số) | Lockout sau N lần |
| 33 | Backup code dùng 2 lần | Reject lần 2 |
| 34 | Bypass MFA bằng cách gọi thẳng endpoint sau MFA | Reject — verify state machine login |

---

## 4. Domain: File Upload

AC thường viết "accept JPG/PNG ≤ 10MB" — miss magic bytes, polyglot, zip-bomb, path traversal.

### 4.1 File format & magic bytes

| # | Edge case | Expected |
|---|---|---|
| 1 | Extension đúng, **magic bytes sai** (vd .pdf nhưng là JPG) | Tin magic bytes, không tin extension |
| 2 | Magic bytes đúng, **extension lạ** (.unknown) | Verify policy — chỉ check magic |
| 3 | **Polyglot file** (vừa là valid JPG vừa là valid HTML/JS) | Reject nếu phát hiện polyglot, hoặc serve với Content-Type ép cứng |
| 4 | File **không có magic bytes** (text plain rename .jpg) | Reject 400 |
| 5 | Magic bytes nằm **không phải đầu file** (offset) | Verify policy strict |

### 4.2 Filename

| # | Edge case | Expected |
|---|---|---|
| 6 | Filename chứa **path traversal** ("../../etc/passwd") | Sanitize hoặc reject |
| 7 | Filename chứa **null byte** ("file.jpg\0.exe") | Strip hoặc reject |
| 8 | Filename chứa **XSS payload** ("<script>.jpg") | Escape khi render hoặc reject |
| 9 | Filename **dài >255 ký tự** | Truncate hoặc reject |
| 10 | Filename chứa **Unicode RTL override** (U+202E) — đảo "exe.jpg" thành "gpj.exe" | Reject hoặc normalize |
| 11 | Filename **dấu cách đầu/cuối** | Trim — verify behavior |
| 12 | Filename **trùng** với file đã có | Verify rule: overwrite, rename auto, hay reject |
| 13 | Filename **rỗng** | Reject 400 |
| 14 | Filename chứa **ký tự đặc biệt OS** (/, \, :, *, ?, ", <, >, \|) | Sanitize hoặc reject |

### 4.3 File size & content

| # | Edge case | Expected |
|---|---|---|
| 15 | File **0 byte** | Reject 400 EMPTY_FILE |
| 16 | File size **= max** (boundary inclusive) | Accept |
| 17 | File size **= max + 1 byte** | Reject 413 |
| 18 | **Zip bomb** (1KB zip giải nén ra 10GB) | Reject — limit decompressed size |
| 19 | **Decompression bomb** PDF/PNG (nested compression) | Reject |
| 20 | File **rất nhiều page/sheet** (vd PDF 10000 trang, XLSX 1M row) | Reject hoặc cap |
| 21 | File chứa **malware signature** (EICAR test) | Reject với code MALWARE_DETECTED |
| 22 | File **encrypted PDF** (password-protected) | Reject hoặc warning |
| 23 | File chứa **embedded executable** (PDF with embedded .exe attachment) | Strip attachment hoặc reject |

### 4.4 Upload mechanics

| # | Edge case | Expected |
|---|---|---|
| 24 | Multipart với **0 file field** | Reject 400 |
| 25 | Multipart với **2+ file field cùng name "file"** | Verify policy: process first, reject, hoặc all |
| 26 | Multipart với **field tên sai** (vd "attachment" thay vì "file") | Reject 422 |
| 27 | **Resume upload bị ngắt giữa chừng** (chunk upload) | Verify tính nhất quán, không có file half-written |
| 28 | **Concurrent upload** cùng file 2 lần | Verify idempotency (hash) hoặc cả 2 thành công với 2 ID khác |
| 29 | Upload **không có Content-Length** | Verify behavior — reject hoặc stream |
| 30 | Upload với **Content-Type sai** (vd application/json) | Reject 415 |

### 4.5 Storage & retrieval

| # | Edge case | Expected |
|---|---|---|
| 31 | Lưu file **disk đầy** | Graceful error, không partial write |
| 32 | Lưu file **vendor S3 timeout** | Retry hoặc graceful fail |
| 33 | Retrieve file **bị xóa khỏi storage** nhưng metadata còn | Verify behavior: 404 hoặc warning |
| 34 | Retrieve file **của user khác** (IDOR) | Reject 403/404 |
| 35 | File **expired** theo retention policy | Verify auto-delete chạy đúng |

---

## 5. Cách dùng reference này

### 5.1 Workflow khi gen test case

```
1. Đọc AC + Trade-off + spec
2. Identify domain feature → 1 trong 4 (AI/OCR / Payment / Auth / File Upload)
3. Gen test case theo AC như bình thường (workflow của SKILL.md)
4. Mở section domain tương ứng trong file này
5. Đối chiếu từng EC trong checklist với TC đã gen
6. Bổ sung TC cho EC còn thiếu HOẶC log GAP nếu AC chưa rõ
7. Chạy validate vòng 1-2-3 trong SKILL.md mục 12
8. Present file kèm coverage summary
```

### 5.2 Khi feature thuộc nhiều domain

VD: API upload ảnh CCCD để OCR → vừa **File Upload** vừa **AI/OCR**. Đọc cả 2 section.

VD: API charge merchant với upload hóa đơn → vừa **Payment** vừa **File Upload** vừa **Auth** (vì có authorization). Đọc cả 3.

### 5.3 Khi feature KHÔNG thuộc 4 domain

Reference này CHƯA cover (vd: messaging, scheduling, CRUD admin panel). Vẫn dùng skill bình thường với references chính (`test-design-techniques.md`, `requirement-doc-to-testcase.md`, `platform-*.md`). Khi onboard dự án mới có domain mới, bổ sung section ở đây hoặc tạo `project-context.md` riêng cho domain.

### 5.4 Anti-pattern khi dùng

| Anti-pattern | Sửa |
|---|---|
| Test 100% EC trong checklist dù scope không có | Chọn theo rủi ro + scope, không phải completionism |
| Bỏ qua reference vì "AC không nhắc EC này" | EC trong reference chính là cái AC hay miss — không skip |
| Coi reference là bắt buộc cứng | Dùng làm prompt suy nghĩ, vẫn cần phán đoán domain context |
| Gen TC từ reference mà không hỏi BA về spec chưa rõ | Log GAP, hỏi BA — không tự bịa |
