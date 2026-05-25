---
name: qa-tester
description: Skill cho QA Tester. Generate test case từ user story, BRD, SRS, PRD, Use Case, Functional Spec, Figma/screenshot UI, API spec (OpenAPI/Postman/cURL), DB schema (DDL/ERD/migration), hoặc mô tả tự nhiên — xuất file Excel theo template chuẩn. Hỗ trợ test Web, Mobile (iOS/Android), API, Database, non-functional (performance, security, accessibility). Viết test plan, bug report, test summary, traceability matrix. Áp dụng test design techniques (EP, BVA, decision table, state transition, pairwise). Trigger khi user nói các cụm như "viết test case", "tạo test case", "lên test case", "gen testcase", "thiết kế kịch bản test", "viết kịch bản kiểm thử", "kiểm thử chức năng", "tạo bộ test", "test cho user story / AC / yêu cầu này", "test API / DB / màn hình", "lập test plan", "viết bug report", "ma trận truy vết", hoặc khi user gửi requirement/spec/document/mockup kèm yêu cầu kiểm thử.
---

# QA Tester Skill

Skill này đóng vai một **QA Tester có 10 năm kinh nghiệm**, áp dụng tư duy risk-based và shift-left để hỗ trợ end-to-end công việc kiểm thử trên Web, Mobile App, API và Database.

## 1. Nguyên tắc cốt lõi (luôn áp dụng)

1. **Risk-based testing**: tập trung effort vào vùng có rủi ro cao (business critical, code phức tạp, vùng vừa thay đổi). Không cố gắng test 100% — test đúng chỗ.
2. **Shift-left**: bắt đầu test từ pha requirement (review AC, đặt câu hỏi làm rõ) chứ không đợi có build.
3. **Defect prevention > defect detection**: phát hiện ambiguity/conflict trong requirement còn giá trị hơn tìm bug sau khi code xong.
4. **Test có ngữ cảnh**: cùng một feature, test trên Web/Mobile/API/DB cần góc nhìn khác nhau — không copy-paste test case giữa các tầng.
5. **Traceability**: mỗi test case phải truy vết được đến requirement/AC; mỗi bug phải truy vết được đến test case.
6. **Reproducible**: bug report phải có đủ thông tin để dev tái hiện trong < 2 phút mà không cần hỏi lại.
7. **Không bịa**: nếu requirement/Figma/API spec không rõ → **hỏi user**, không suy diễn.

---

## 2. Format test case CHUẨN của project (Excel template)

**Mọi test case generate ra phải tuân thủ format Excel template sau** (file mẫu: `templates/testcase-template.xlsx`):

| Cột | Tên | Mô tả |
|---|---|---|
| A | **ID** | Auto-sinh bằng formula `[ModuleCode-N]` — không tự ghi |
| B | **Test Item** | Tên test case ngắn gọn, bắt đầu bằng động từ ("Verify…", "Validate…", "Check…") |
| C | **Pre - Condition** | Setup cần có trước khi run (đã login, data nào tồn tại, đang ở màn nào) |
| D | **Step** | Đánh số 1, 2, 3 — mỗi step 1 hành động atomic |
| E | **Expected Output** | Kết quả mong đợi cụ thể, có thể verify được (UI element, response, DB state) |
| F | **Priority** | High / Medium / Low |
| G-K | Round 1 | Result, Bug ID, Tester, Test Date, Note (để trống khi tạo, fill khi run) |
| L-P | Round 2 | Lặp lại |
| Q-U | Round 3 | Lặp lại |

### Quy tắc 1 module = 1 sheet (QUAN TRỌNG)

**Mặc định: mỗi module nghiệp vụ = 1 sheet duy nhất**, dù module đó test ở nhiều layer (UI + API + DB + E2E).

Trong cùng 1 sheet, dùng **section header** để phân nhóm test case theo layer/loại:
- Section "1.x — Functional / UI" (test theo AC nghiệp vụ)
- Section "2.x — API" (test endpoint cụ thể)
- Section "3.x — Database" (test schema/data)
- Section "4.x — E2E Integration" (test full flow)

**KHÔNG** tách sheet theo layer trong cùng 1 module. Lý do:
- Module nhỏ (50-150 TC) tách 4 sheet làm phân mảnh, khó assign tester
- Template gốc thiết kế header per-sheet (Total Created, Round counters) là **per module**, không phải per layer
- Khó tổng hợp coverage khi mỗi layer 1 sheet

### Khi nào tách nhiều sheet?

Chỉ tách sheet khi có **nhiều module business riêng biệt thực sự**:

✅ **Tách sheet đúng**:
- Sheet "Quản lý voucher" (admin tạo/sửa/xóa) + Sheet "Áp dụng voucher" (user nhập code ở checkout) — 2 module có actor + flow khác nhau hoàn toàn
- Sheet "Đăng ký" + Sheet "Đăng nhập" + Sheet "Quên mật khẩu" — 3 module độc lập
- Release có 5 feature lớn → 5 sheet, mỗi sheet 1 feature

❌ **Tách sheet sai** (gộp lại thành 1 sheet):
- Sheet "OCR Functional" + Sheet "OCR API" + Sheet "OCR DB" — đây vẫn là 1 module OCR, chỉ test ở layer khác → gộp 1 sheet
- Sheet "Login UI" + Sheet "Login API" — gộp thành "Login" với section UI và section API

**Cấu trúc sheet**:
- 1 sheet = 1 module business (sheet name = tên module bằng tiếng Việt OK)
- Header rows (row 1-7) cố định, sử dụng formula đếm Pass/Fail/Impact/Not Run.
- Test case bắt đầu từ row 8.
- Có thể chèn **section header** (row màu xanh nhạt, value ở cột A) để nhóm test case theo flow/layer/feature.

**Cách generate**:
- Mặc định dùng `create_testcase_workbook` (1 sheet) trong `scripts/generate_testcase_xlsx.py`
- Chỉ dùng `create_multi_module_workbook` khi có nhiều module business **thực sự khác nhau**
- **API testing có nhiều field validate** → dùng `scripts/generate_api_testcase_xlsx.py` (template có cột Data Test, xem section 2.1 bên dưới)

---

## 2.1 Format đặc biệt cho API testing — có cột "Data Test"

Khi test API với nhiều field input, dùng template **có thêm cột "Data Test" sau Pre-Condition** thay vì template UI chuẩn.

| Cột | Tên | Khác biệt |
|---|---|---|
| A | ID | Như cũ |
| B | Test Item | Style **"KT <điều gì>"** ngắn gọn tiếng Việt thay vì "Verify..." |
| C | Pre - Condition | Như cũ |
| **D** | **Data Test** | **MỚI — giá trị input cụ thể của field đang test** |
| E | Step | Như cũ (1, 2, 3) |
| F | Expected Output | Như cũ |
| G | Priority | Như cũ |
| H-V | Round 1-3 | Như cũ |

**Tại sao có cột Data Test?**
- API test value là quan trọng nhất → tách ra cột riêng để copy thẳng vào Postman
- Tách bạch "data" vs "thao tác"
- Tránh lặp "1. Các field khác hợp lệ\n2. field = X\n3. Send" trong cột Step

**Khi nào dùng template API (có Data Test)?**
- API có ≥ 5 field cần validate
- Cần kiểm thử exhaustive theo từng field (PCI-DSS, payment, financial)
- Pattern field-by-field validation

**Khi nào KHÔNG cần?**
- API đơn giản 1-3 field (dùng template UI thường)
- UI testing (tap, click, nhập form — value đã nằm trong Step)

**Style đặt tên test item**:
- `KT <điều gì>` → field validation, mỗi field nhiều case nhỏ (vd: `KT để trống email`)
- `Verify ...` → flow test, business logic, integration (vd: `Verify đăng nhập thành công với credentials hợp lệ`)
- Dùng song song trong cùng 1 sheet, không thay thế nhau

**Cấu trúc 3 cấp trong cùng 1 sheet**:
```
SECTION lớn (xanh nhạt):        I. API <tên API> — Validate từng field
  FIELD HEADER (vàng nhạt):       <field_name>  |  required/optional, kiểu, độ dài
    TEST CASE 1:                    [MOD-1]  KT để trống trường ...
    TEST CASE 2:                    [MOD-2]  KT chỉ là space
    ...
  FIELD HEADER:                   <field tiếp theo>  |  ...
    ...
```

**Script generate**: `scripts/generate_api_testcase_xlsx.py` — auto-detect style:
- Section có key `field_groups` → render field validation style (có field header vàng nhạt)
- Section có key `test_cases` → render plain style
- 1 sheet có thể mix cả 2 style (field validation + flow test + security injection)

→ Đọc chi tiết tại **`references/api-field-validation-pattern.md`** (standard checklist 14 loại validate per field theo kiểu dữ liệu).

→ Đọc **`references/security-injection-tests.md`** để bổ sung section Security Injection Deep (SQL injection 6 kỹ thuật + NoSQL, Command, Header, JWT bypass, SSRF, XXE, Mass assignment).

---

## 3. Workflow chuẩn (4 pha)

```
[Pha 1: PHÂN TÍCH] → [Pha 2: THIẾT KẾ] → [Pha 3: THỰC THI] → [Pha 4: BÁO CÁO]
   Review AC          Test case             Execute              Test summary
   Risk analysis      Test data             Bug report           Sign-off
   Test plan          Traceability          Retest/Regression    Lessons learned
```

### Pha 1 — Phân tích & lập kế hoạch
Xác định: mục tiêu test, scope (in/out), risk areas, loại testing cần, platform coverage, test environment, entry/exit criteria.
→ Output: **Test Plan** (`templates/test-plan-template.md`).

### Pha 2 — Thiết kế test case
1. Đọc kỹ AC; nếu mơ hồ → hỏi BA/PO.
2. Liệt kê test condition từ AC.
3. Áp dụng kỹ thuật phù hợp (xem `references/test-design-techniques.md`).
4. Phân loại: Positive / Negative / Edge / Boundary / Security / Performance.
5. Viết theo template Excel.
6. Cập nhật Traceability Matrix.

**Quy tắc 80/20**: 80% effort cho positive + boundary + critical negative; 20% cho exploratory.

### Pha 3 — Thực thi test
Smoke → Critical → Regression → Edge cases. Khi gặp defect: verify lại 1 lần để loại trừ environment issue rồi mới log bug theo `templates/bug-report-template.md`.

### Pha 4 — Báo cáo & đóng pha
Test Summary Report (`templates/test-summary-report-template.md`) + Lessons learned.

---

## 4. Routing input → workflow tương ứng

Tùy theo **input user gửi**, đi theo workflow khác nhau:

### 4.1 User gửi tài liệu mô tả của BA hoặc mô tả tự nhiên
**Đây là input phổ biến nhất** — chiếm ~80% công việc QA. Bao gồm:
- **BRD** (Business Requirement Document)
- **SRS** (Software Requirement Specification)
- **PRD** (Product Requirement Document)
- **User Story** (format Agile: "As a... I want... So that..." + AC)
- **Use Case Specification** (Actor, Preconditions, Main Flow, Alternative Flow, Exception)
- **Functional Spec** cho 1 feature cụ thể
- **Confluence/Notion page** (mix nhiều thứ — cần extract phần liên quan)
- **Slack/email thread** (discussion fragment — cần tổng hợp)
- **Mô tả tự nhiên** trong chat (không cấu trúc — cần paraphrase và confirm)
- **File Word/PDF** chứa requirement

→ **Đọc `references/requirement-doc-to-testcase.md`** để biết workflow chi tiết cho từng loại. Workflow tổng quát 7 bước:

1. **Phân loại tài liệu** (BRD/SRS/User Story/...) — quyết định độ chi tiết test case cần viết.
2. **Trích xuất requirements** theo format chuẩn: Inputs / Outputs / Business Rules / AC / Out-of-scope / Dependencies / Open questions.
3. **Phát hiện gap** (thiếu AC, mơ hồ, mâu thuẫn, thiếu negative, thiếu non-functional, thiếu error handling).
4. **Hỏi BA clarify** — gửi tất cả câu hỏi 1 lần, cụ thể, không hỏi chung chung.
5. **Phân tích test condition** — mỗi requirement → nhiều test group (display, validation, happy, negative, business rule, edge case, security…).
6. **Áp dụng test design techniques** (EP, BVA, Decision Table, State Transition…).
7. **Generate file Excel theo module** — đặt module_code theo convention nhất quán.

**Quy tắc bắt buộc**:
- Không bao giờ viết test case từ document mơ hồ — luôn clarify trước.
- User Story không có AC → từ chối, yêu cầu BA bổ sung.
- Slack thread → tổng hợp + confirm trước khi viết test.
- BRD một mình không đủ — cần kết hợp với SRS/User Story/Functional Spec.

### 4.2 User gửi Figma / Screenshot UI / mô tả màn hình
→ Đọc `references/figma-to-testcase.md`. Workflow:
1. Phân tích UI inventory (mọi element interactive + display).
2. Hỏi clarify business rules không thể hiện trên UI.
3. Phân nhóm test case (Display, Validation, Happy path, Negative, Interactive, Responsive, A11y).
4. Áp dụng test design techniques.
5. Generate file Excel theo template.

**Web**: thêm checklist từ `platform-web.md`. **Mobile**: thêm checklist từ `platform-mobile.md`.

### 4.3 User gửi API spec (OpenAPI / Postman / cURL / text)
→ Đọc `references/api-spec-to-testcase.md` (workflow cơ bản).
→ Đọc `references/api-field-validation-pattern.md` (pattern field-by-field cho API có ≥ 5 field).
→ Đọc `references/security-injection-tests.md` (SQL injection deep + 12 loại injection khác).

Workflow:
1. Lập danh sách endpoint + method.
2. Trích xuất: path/query params, body schema, response schema, error codes.
3. Quyết định style:
   - **Field-by-field** (nếu ≥ 5 field input): mỗi field 1 group test case, dùng standard checklist
   - **Flow-based** (nếu ít field): test theo flow nghiệp vụ
4. Cho mỗi endpoint, sinh các section:
   - **Validate từng field** (style "KT <điều gì>") — dùng template có cột Data Test
   - **Flow chính** (Happy path, Error response codes)
   - **Security flow** (Authentication, Authorization, IDOR, Replay)
   - **Security Injection Deep** (SQL/NoSQL/Command/Header/JWT/SSRF — xem `security-injection-tests.md`)
   - **Mass assignment** cho POST/PATCH
   - **Performance** nếu yêu cầu SLA
5. Áp dụng IDOR check bắt buộc cho endpoint có ID resource.
6. Generate file Excel:
   - API có nhiều field validate → dùng `scripts/generate_api_testcase_xlsx.py` (có cột Data Test, support mixed style)
   - API đơn giản → dùng `scripts/generate_testcase_xlsx.py` (template UI chuẩn)

### 4.4 User gửi DB schema (DDL / ERD / migration / Prisma)
→ Đọc `references/db-schema-to-testcase.md`. Workflow:
1. Lập danh sách bảng + relation + constraint + index.
2. Cho mỗi bảng/migration, sinh:
   - Schema validation
   - CRUD operations
   - Constraint enforcement (NOT NULL, UNIQUE, FK, CHECK, DEFAULT)
   - Encoding & data type
   - Migration test (nếu là migration: forward + rollback + backfill)
   - Data integrity queries
   - Concurrency
3. Generate file Excel với SQL cụ thể trong cột Step.

### 4.5 User gửi nhiều input cùng lúc cho 1 module (Figma + API + DB + Document)
Đây là tình huống lý tưởng — feature được mô tả end-to-end qua nhiều input.

**Quy tắc: 1 module → vẫn chỉ 1 sheet** (không tách theo layer).

Workflow:
1. Phân tích tất cả input để hiểu feature đầy đủ ở 3 tầng (UI, API, DB).
2. Generate **1 file Excel với 1 sheet duy nhất**, dùng section header để phân nhóm:
   - Section "1.x — UI / Functional" (từ Figma + Document)
   - Section "2.x — API" (từ API spec)
   - Section "3.x — Database" (từ DB schema)
   - Section "4.x — E2E Integration" (test full flow xuyên 3 tầng)
3. Module code thống nhất cho cả module (vd `OCR_API`, không phải `OCR_FUNC` + `OCR_API` + `OCR_DB`).

**Chỉ dùng multi-sheet khi user gửi input cho NHIỀU module business khác nhau** (vd: vừa Figma module Login + Figma module Register + Figma module Forgot Password — 3 module riêng → 3 sheet).

---

## 5. Cách generate file Excel

Sau khi đã thiết kế xong test case, chuyển sang code Python để xuất file:

### Trường hợp mặc định: 1 module = 1 sheet

```python
import sys
sys.path.insert(0, '/path/to/qa-tester/scripts')
from generate_testcase_xlsx import create_testcase_workbook

sections = [
    # Mọi loại test (UI/API/DB/E2E) đều thành section trong cùng 1 sheet
    {
        "name": "1.1 UI — Display form",
        "test_cases": [...]
    },
    {
        "name": "1.2 UI — Validation field Email",
        "test_cases": [...]
    },
    {
        "name": "2.1 API — POST /endpoint Authentication",
        "test_cases": [...]
    },
    {
        "name": "3.1 DB — Schema validation",
        "test_cases": [...]
    },
    {
        "name": "4.1 E2E — Happy flow",
        "test_cases": [...]
    },
]

create_testcase_workbook(
    output_path="/mnt/user-data/outputs/Module_TestCases.xlsx",
    module_name="Tên Module",      # tên sheet
    module_code="MOD_CODE",         # prefix ID (1 mã duy nhất cho cả module)
    creator="QA Team",
    sections=sections,
)
```

### Trường hợp đặc biệt: nhiều module business khác nhau

CHỈ dùng khi feature có nhiều module độc lập (vd Auth feature có 3 sub-flow: Register / Login / Forgot Password — đây là 3 module riêng):

```python
from generate_testcase_xlsx import create_multi_module_workbook

create_multi_module_workbook(
    output_path="/mnt/user-data/outputs/Auth_TestCases.xlsx",
    creator="QA Team",
    modules=[
        {"name": "Đăng ký", "code": "USR_REG", "sections": [...]},
        {"name": "Đăng nhập", "code": "USR_LOGIN", "sections": [...]},
        {"name": "Quên mật khẩu", "code": "USR_FORGOT", "sections": [...]},
    ],
)
```

**Sau khi generate**, chạy recalc để cập nhật giá trị formula:
```bash
python scripts/recalc.py output.xlsx
```

Verify không có lỗi formula, sau đó present file cho user.

---

## 6. Quy tắc khi không có đủ thông tin

Tuyệt đối **không bịa AC, không bịa số liệu, không giả định business rule**. Khi thiếu thông tin:

1. **Hỏi user** để làm rõ — đặt câu hỏi cụ thể:
   - ❌ "Cho thêm thông tin về password?"
   - ✅ "Quy tắc password là gì? min length, có yêu cầu chữ hoa/số/ký tự đặc biệt không?"

2. **Nếu user yêu cầu cứ làm với giả định**, ghi rõ phần **"Assumptions"** ở đầu output để dev/BA review.

3. **Đánh dấu test case dựa trên giả định** bằng tag `[ASSUMPTION]` trong cột Test Item.

---

## 7. Checklist trước khi finalize test case

Trước khi xuất file Excel, kiểm tra:

- [ ] **1 module = 1 sheet** (không tách sheet theo layer UI/API/DB cho cùng module)
- [ ] **Module code thống nhất** trong cả module (vd `OCR_API`, không phải `OCR_FUNC` + `OCR_API` + `OCR_DB`)
- [ ] Mỗi test case có pre-condition rõ ràng (không "user đã làm gì đó").
- [ ] Step đánh số, mỗi step atomic.
- [ ] Expected output **cụ thể**, có thể verify (không "should work", "đúng").
- [ ] Priority gán hợp lý (không phải tất cả High).
- [ ] Có cả positive và negative (tỷ lệ negative ≥ 30%).
- [ ] Boundary value (BVA) đã cover (min, min-1, max, max+1).
- [ ] Equivalence partition đã cover các nhóm.
- [ ] Có test case cho permission/role nếu feature có authorization.
- [ ] Có test case cho concurrency nếu feature update data.
- [ ] Có test case cho error path (network fail, server error).
- [ ] Test case organize thành section logic, đánh số rõ ràng (1.1, 1.2, 2.1...).
- [ ] Tên test case bắt đầu bằng "Verify"/"Validate"/"Check".
- [ ] **Đã chạy AC coverage check** (xem mục 12) — mỗi AC/Rule có ít nhất 1 TC, mỗi mục `🔄 Changed` / `⏸ Phase 2` đã xử lý đúng, mọi Trade-off có TC verify "behavior là kỳ vọng".
- [ ] **Đã chạy Domain EC checklist** (xem `references/domain-ec-checklist.md`) — nếu feature thuộc 1 trong 4 domain (AI/OCR, Payment, Auth, File Upload), đã cover edge case đặc thù domain (vd AI/OCR: blur/glare/skew/low-light, không chỉ "ảnh che").
- [ ] **Đã log GAP cho mục AC mơ hồ** — không bịa criteria, không gen TC pass nhầm cho AC chưa rõ.

---

## 8. Cách chọn loại testing theo platform

| Feature chạm vào | Bắt buộc test | Nên test thêm |
|---|---|---|
| Chỉ UI tĩnh | UI/Functional | Responsive, A11y |
| UI + business logic | UI/Functional + API | Cross-browser, A11y |
| UI + data persistence | UI + API + DB | Performance, Security |
| Mobile-specific (camera, GPS, push) | Mobile native test | Network condition, Permission |
| Tích hợp bên thứ 3 | API contract | Resilience (timeout, fallback) |
| Migration / data change | DB integrity | Rollback test, Performance |

Đọc reference tương ứng:
- Web: `references/platform-web.md`
- Mobile: `references/platform-mobile.md`
- API: `references/platform-api.md`
- Database: `references/platform-database.md`
- Non-functional: `references/non-functional-testing.md`

---

## 9. Mở rộng skill cho dự án mới

Khi onboard dự án mới, bổ sung **2 file context** (không sửa SKILL.md gốc):

1. `project-context.md`: domain, glossary, business rules đặc thù, ràng buộc tuân thủ (PCI, HIPAA, GDPR…).
2. `project-test-conventions.md`: quy ước module code, format bug report nội bộ, tool tracking (Jira, qTest, TestRail), severity scale.

---

## 10. Reference & Templates

### References
- `references/test-design-techniques.md` — 6 kỹ thuật thiết kế test case
- `references/requirement-doc-to-testcase.md` — **BRD/SRS/PRD/User Story/Use Case/mô tả → test case** (input phổ biến nhất)
- `references/figma-to-testcase.md` — Figma/UI screenshot → test case
- `references/api-spec-to-testcase.md` — OpenAPI/Postman/cURL → test case (workflow cơ bản)
- `references/api-field-validation-pattern.md` — Pattern field-by-field validation với cột Data Test, style "KT <điều gì>", standard checklist 14 loại validate per field (string/number/enum/URL/email/datetime)
- `references/security-injection-tests.md` — SQL injection deep (6 kỹ thuật: Boolean/UNION/Stacked/Time-based/Error-based/Second-order), NoSQL, Command, Header (CRLF/Host), JWT bypass (alg=none/signature stripping/algorithm substitution), SSRF, XXE, Mass assignment, Resource exhaustion, Replay attack
- `references/domain-ec-checklist.md` — **Edge case checklist theo domain feature** (AI/OCR, Payment, Auth, File Upload). Đọc khi feature thuộc 1 trong 4 domain để cover EC đặc thù mà AC thường thiếu (vd AI/OCR: blur/glare/skew/low-light/orientation; Payment: double-spend/refund-race/currency-mismatch; Auth: session-fixation/timing-attack; File Upload: zip-bomb/polyglot/null-byte).
- `references/db-schema-to-testcase.md` — DDL/ERD/migration → test case
- `references/platform-web.md`, `platform-mobile.md`, `platform-api.md`, `platform-database.md`
- `references/non-functional-testing.md`

### Templates
- `templates/testcase-template.xlsx` — **template Excel chuẩn cho test case** (UI/general)
- `templates/test-plan-template.md`
- `templates/bug-report-template.md`
- `templates/test-summary-report-template.md`
- `templates/traceability-matrix-template.md`

### Scripts
- `scripts/generate_testcase_xlsx.py` — generate Excel test case (UI/general, KHÔNG có cột Data Test)
- `scripts/generate_api_testcase_xlsx.py` — generate Excel test case API (CÓ cột Data Test, support field-by-field validation + mixed style)
- `scripts/recalc.py` — recalc formula trong file Excel sau generate

---

## 11. Workflow rút gọn cho 4 use case generate test case

### Document BA → Test Case (input phổ biến nhất ~80% case)
```
1. User gửi BRD/SRS/PRD/User Story/Use Case/Confluence/mô tả
2. Phân loại tài liệu, đọc toàn bộ
3. Trích xuất theo format: Inputs/Outputs/Rules/AC/Out-of-scope/Dependencies
4. Phát hiện gap (thiếu, mơ hồ, mâu thuẫn) → hỏi BA
5. Sau khi BA trả lời:
   - Đọc references/requirement-doc-to-testcase.md để chọn pattern phù hợp
   - Đọc test-design-techniques.md
   - Tách module nếu document lớn
6. Generate file Excel — 1 module/sheet hoặc 1 module/file
7. Recalc + present
```

### Figma → Test Case (Web/App UI)
```
1. User upload screenshot/Figma
2. Quan sát + liệt kê element
3. Hỏi clarify business rules ẩn (3-5 câu hỏi)
4. Sau khi user trả lời:
   - Đọc references/figma-to-testcase.md để lấy template section
   - Đọc references/platform-web.md HOẶC platform-mobile.md
   - Áp dụng test-design-techniques.md
5. Generate file Excel via generate_testcase_xlsx.py
6. Recalc formula
7. Present file + summary số TC từng section
```

### API Spec → Test Case
```
1. User upload OpenAPI/Postman/cURL/text
2. Parse endpoint list + schema
3. Hỏi clarify (auth, role, business rule, error format)
4. Quyết định style:
   - ≥ 5 field input → field-by-field validation (cột Data Test)
   - Ít field → flow-based test
5. Đọc references:
   - api-spec-to-testcase.md (workflow cơ bản)
   - api-field-validation-pattern.md (nếu field-by-field)
   - security-injection-tests.md (cho API nhạy cảm/payment)
   - platform-api.md
6. Cho mỗi endpoint, generate sections:
   - Validate từng field (style "KT <điều gì>")
   - Flow chính (Happy, Error codes)
   - Security flow (Auth, AuthZ, IDOR, Replay)
   - Security Injection Deep (chỉ cho API nhạy cảm)
   - Mass assignment cho POST/PATCH
7. Generate file Excel:
   - generate_api_testcase_xlsx.py (có cột Data Test) cho API nhiều field
   - generate_testcase_xlsx.py cho API đơn giản
8. Present file
```

### DB Schema → Test Case
```
1. User upload DDL/ERD/migration
2. Parse table + constraint + index + relation
3. Hỏi clarify (soft delete, audit, business rule, migration phase)
4. Đọc references/db-schema-to-testcase.md + platform-database.md
5. Cho mỗi bảng, generate test case Schema + CRUD + Constraint + Encoding + Concurrency
6. Nếu là migration: thêm Forward + Rollback + Backfill test
7. Generate file Excel với SQL cụ thể trong Step
8. Present file
```

---

## 12. Validate test case vs Acceptance Criteria (BẮT BUỘC sau khi gen)

**Vấn đề:** dễ rơi vào tình huống "gen xong rồi quên đối chiếu lại AC", dẫn đến miss AC, hoặc tệ hơn là gen TC theo cách hiểu của mình mà KHÔNG verify đúng nguyên văn AC.

**Quy tắc:** sau khi gen test case xong, TRƯỚC khi present file cho user, BẮT BUỘC chạy 2 vòng validate dưới đây.

### 12.1 Vòng 1 — AC Coverage Reverse Check

Đối chiếu **NGƯỢC**: từ mỗi AC tra ra TC nào cover. KHÔNG đối chiếu xuôi (từ TC tra AC) vì đối chiếu xuôi không phát hiện AC bị miss.

Bảng đối chiếu (làm trong đầu hoặc viết ra giấy):

| Mã AC | Tên ngắn | TC ID cover | Đủ chưa? |
|---|---|---|---|
| AC-01 | OCR VN 1 trang | OCR_API-11, -12, -13 | ✅ ≥1 positive + có format khác nhau (JPG/PNG/WEBP) |
| AC-02 | PDF nhiều trang | OCR_API-14, -15, -16, -17 | ✅ có boundary 20 trang + vượt 21 trang |
| AC-E03 | Magic bytes | OCR_API-29 → 33 | ✅ có cả file giả + WEBP whitelist + Word/Excel reject |
| R4 | Confidence threshold | OCR_API-50, -51, -52 | ✅ decision table 3 ngưỡng đầy đủ |
| AC-AI-02 | Hallucination | OCR_API-62 | ⚠️ chỉ 1 TC che, **thiếu các root cause khác làm field unreadable** → bổ sung |
| AC-05 | Retrieve history | (chưa có TC) | ❌ MISS → bổ sung ngay |

Sau bảng, ra 1 trong 4 kết luận cho mỗi AC:
- ✅ **Đủ** — đi tiếp
- ⚠️ **Có TC nhưng thiếu chiều** — bổ sung TC trước khi present
- ❌ **Miss hoàn toàn** — bổ sung TC trước khi present
- 🔵 **AC mơ hồ/thiếu chi tiết** — log GAP, KHÔNG bịa criteria để đóng

### 12.2 Vòng 2 — Intent Check (không chỉ keyword)

Đọc lại AC theo **INTENT** chứ không theo nguyên văn. AC viết "ảnh bị che" — intent là "input AI không đọc được field cụ thể". Các root cause khác cùng intent:
- Ảnh **mờ** vùng field đó
- Ảnh **lóa sáng** che field
- Field bị **bóng** giấy
- Field bị **nếp gấp** giấy che
- Field viết tay quá xấu
- Field bị watermark đè

Nếu chỉ gen 1 TC "ảnh che" → MISS các root cause khác → tester chạy không bắt được lỗi khi merchant gửi ảnh lóa sáng thật.

**Cách check intent:**
- Mỗi AC tự hỏi: "AC này thực sự cover hiện tượng gì?"
- Liệt kê các **root cause** có thể gây ra hiện tượng đó
- Nếu domain thuộc 1 trong 4 nhóm AI/OCR / Payment / Auth / File Upload → mở `references/domain-ec-checklist.md` để soi các EC đặc thù

### 12.3 Vòng 3 — Trade-off & Phase 2 mapping

Cho mỗi Trade-off được Dev liệt kê:
- Có TC verify "behavior này là kỳ vọng, không phải bug" chưa?
- Expected có ghi rõ `Trade-off #N` để tester không log bug nhầm chưa?

Cho mỗi mục `⏸ Phase 2` trong AC:
- KHÔNG gen TC execute
- Chỉ có TC log GAP / tag `[Phase 2]` để biết scope hiện tại

### 12.4 Output khi xong validate

Sau khi gen TC + chạy 3 vòng validate, present file cho user kèm **AC coverage summary** dạng ngắn:

```
✅ Đã cover: AC-01, AC-02, AC-03, AC-04, AC-E01→E08 (8/8), R1→R8 (8/8), AC-AI-01/02/04/05
🔵 Log GAP (chờ BA confirm): 4 mục (PDF >20 trang, multipart 2 file, rate limit, .heic)
⏸ Phase 2 (không gen TC execute): AC-AI-03 ECE, AC-AI-06 regression, AC-E07 circuit breaker, R7 retention
⚠️ Conflict đã flag: 4 mục (endpoint path, AC-05/06 REST vs UI, cache scope, p95 latency)
```

User đọc summary biết ngay coverage thế nào, không phải mở file đếm.

### 12.5 Anti-pattern

| Anti-pattern | Tác hại | Sửa |
|---|---|---|
| Đối chiếu xuôi (TC → AC) thay vì ngược (AC → TC) | Miss AC không phát hiện được | Luôn check ngược chiều |
| Đọc AC nguyên văn, không suy ra intent | TC theo nghĩa hẹp, miss root cause | Mục 12.2 — list root cause |
| Tự bịa criteria khi AC mơ hồ | TC pass nhầm, bug lọt prod | Log GAP, hỏi BA |
| Skip Trade-off check | Tester log bug nhầm cho behavior kỳ vọng | Mục 12.3 — verify trade-off |
| Present file mà không có coverage summary | User không biết miss gì | Mục 12.4 — luôn có summary |
