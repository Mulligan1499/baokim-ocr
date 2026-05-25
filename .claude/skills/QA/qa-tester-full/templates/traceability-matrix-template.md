# Requirement Traceability Matrix (RTM)

> Mục đích: chứng minh **mọi requirement đều được test**, **mọi test case đều có gốc requirement**.
> Là tài liệu sống — update suốt phase test.

---

## Cấu trúc cột chuẩn

| Cột | Mô tả |
|---|---|
| **Req ID** | ID của requirement / user story (vd PROJ-123) |
| **Req Description** | Tóm tắt 1 dòng |
| **AC ID** | AC con trong story (vd AC1, AC2) |
| **AC Description** | Tóm tắt AC |
| **TC IDs** | Danh sách test case cover AC này (vd TC_LOGIN_001, TC_LOGIN_002) |
| **Test Type** | Functional / Negative / Boundary / Security / Perf / A11y |
| **Priority** | P1 / P2 / P3 / P4 |
| **Execution Status** | Not Started / In Progress / Done |
| **Pass Rate** | % TC pass / total TC cho AC này |
| **Bug IDs** | Bug tìm được khi test AC này |
| **Coverage Status** | ✅ Covered / ⚠️ Partial / ❌ Not covered |

---

## Ví dụ minh họa

| Req ID | Req Description | AC ID | AC Description | TC IDs | Test Type | Priority | Status | Pass Rate | Bugs | Coverage |
|---|---|---|---|---|---|---|---|---|---|---|
| PROJ-123 | User Login | AC1 | Login với email + password đúng → vào dashboard | TC_LOGIN_001, TC_LOGIN_002 | Functional | P1 | Done | 100% (2/2) | — | ✅ |
| PROJ-123 | User Login | AC2 | Login với password sai → error message | TC_LOGIN_003, TC_LOGIN_004 | Negative | P1 | Done | 100% (2/2) | — | ✅ |
| PROJ-123 | User Login | AC3 | Password ≥ 8 ký tự, có chữ hoa, số, ký tự đặc biệt | TC_LOGIN_005..TC_LOGIN_012 | Boundary + EP | P1 | Done | 87.5% (7/8) | PROJ-1102 (S3) | ⚠️ |
| PROJ-123 | User Login | AC4 | Khóa account sau 5 lần fail | TC_LOGIN_013, TC_LOGIN_014 | Security | P1 | Done | 100% | — | ✅ |
| PROJ-123 | User Login | AC5 | Forgot password gửi email reset | TC_LOGIN_015..TC_LOGIN_017 | Functional | P2 | In Progress | — | — | ⚠️ |
| PROJ-124 | User Logout | AC1 | Logout xóa session | TC_LOGIN_018 | Functional | P1 | Done | 100% | — | ✅ |
| PROJ-125 | Performance | AC1 | API login P95 < 500ms với 1000 concurrent | TC_PERF_001 | Performance | P2 | Done | Pass | — | ✅ |

---

## Kiểm tra hoàn thiện ma trận

Trước khi sign-off test phase, ma trận phải đảm bảo:

### 1. Forward traceability — mỗi requirement đều có TC
```
SELECT Req.ID
FROM Requirements Req
LEFT JOIN RTM ON RTM.req_id = Req.ID
WHERE RTM.tc_ids IS NULL OR RTM.tc_ids = ''
```
→ Kết quả phải = 0. Nếu không, có requirement chưa test.

### 2. Backward traceability — mỗi TC đều có gốc requirement
```
SELECT TC.ID
FROM TestCases TC
WHERE TC.linked_req IS NULL
```
→ Kết quả phải = 0. Nếu không, có TC "mồ côi" — có thể là test thừa hoặc đang test thứ không có trong scope.

### 3. Coverage status
- ✅ **Covered**: ≥ 1 TC pass cho AC.
- ⚠️ **Partial**: có TC nhưng không pass hết, hoặc chưa test hết các nhánh.
- ❌ **Not covered**: chưa có TC.

→ Trước release, **không được có** ❌. Phải có hoặc ✅ hoặc ⚠️ với justification.

---

## Cập nhật RTM khi requirement thay đổi

Khi BA/PM thay đổi AC giữa phase:

1. Đánh dấu **dòng cũ** với status "Obsolete".
2. Thêm dòng mới cho AC mới.
3. Re-design TC: TC cũ có còn dùng được? Cần thêm TC mới?
4. Update **change log**:

```
2024-11-10 — PROJ-123 AC3: thay đổi từ "≥ 6 ký tự" thành "≥ 8 ký tự".
  - Obsolete: TC_LOGIN_005, TC_LOGIN_006 (test với 5, 6 ký tự)
  - New: TC_LOGIN_005a, TC_LOGIN_005b, TC_LOGIN_005c (test với 7, 8, 9 ký tự)
  - Impact: PROJ-456 (Sign-up) cũng dùng password rule — cần update RTM cho nó.
```

---

## RTM cho integration / multi-feature

Khi AC liên quan đến nhiều module:

| Req ID | AC | Module 1 TC | Module 2 TC | Integration TC | Status |
|---|---|---|---|---|---|
| PROJ-200 | Đặt hàng + thanh toán + gửi email | TC_ORDER_010 | TC_PAY_005 | TC_E2E_002, TC_E2E_003 | ✅ |

→ Đặc biệt quan trọng: **Integration TC** test toàn flow đầu cuối, không chỉ test từng module isolated.

---

## RTM cho non-functional

Non-functional requirement cũng cần trong RTM:

| Req ID | Type | Description | TC IDs | Status |
|---|---|---|---|---|
| NFR-01 | Performance | API checkout P95 < 1s | TC_PERF_010 | ✅ |
| NFR-02 | Security | Mật khẩu lưu hash bcrypt | TC_SEC_005 | ✅ |
| NFR-03 | Availability | 99.9% uptime | (operational, không phải pre-release TC) | N/A |
| NFR-04 | A11y | WCAG 2.1 AA | TC_A11Y_001..TC_A11Y_020 | ✅ |
| NFR-05 | Compatibility | Chrome/Safari/Firefox/Edge latest | TC_COMPAT_001..TC_COMPAT_010 | ✅ |

---

## Excel / Tool format

Ma trận có thể duy trì trong:

- **Excel** — phù hợp dự án nhỏ, dễ chia sẻ. Dùng filter, conditional formatting.
- **Jira** — link TC với Story qua "Tests" link type. Dùng plugin Xray hoặc Zephyr để view RTM.
- **TestRail** / **qTest** — built-in RTM view.
- **Confluence + Jira macro** — generate RTM tự động từ Jira.

→ Khi user yêu cầu xuất RTM ra Excel → dùng skill `xlsx`, format conditional: ✅ xanh, ⚠️ vàng, ❌ đỏ.

---

## Định kỳ review

- **Cuối mỗi sprint**: QA Lead review ma trận với PM — đảm bảo không có gap.
- **Trước release**: ma trận là input của Test Summary Report.
- **Sau production bug**: kiểm tra ma trận có gap đã dẫn đến bug không → cải tiến.
