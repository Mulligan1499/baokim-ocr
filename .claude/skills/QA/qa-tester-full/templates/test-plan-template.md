# Test Plan — [Tên dự án/Tên feature]

> **Hướng dẫn dùng**: copy file này, điền vào các phần `[...]`, xóa hướng dẫn trong block trích dẫn `>` trước khi gửi đi.
> Mọi mục đánh dấu **[bắt buộc]** không được bỏ trống. Mục **[tùy chọn]** điền nếu áp dụng.

---

## 1. Thông tin chung [bắt buộc]

| Hạng mục | Giá trị |
|---|---|
| **Tên dự án** | [...] |
| **Phạm vi test** | [Sprint/Feature/Release version] |
| **Phiên bản tài liệu** | v1.0 |
| **Tác giả** | [Tên QA Lead] |
| **Ngày tạo** | YYYY-MM-DD |
| **Ngày phê duyệt** | YYYY-MM-DD |
| **Người phê duyệt** | [PM/Tech Lead] |

---

## 2. Mục tiêu test [bắt buộc]

> Trả lời: tại sao test? đạt được gì sau khi test xong?

- [Mục tiêu 1, vd: Verify chức năng đăng ký user mới hoạt động đúng AC]
- [Mục tiêu 2, vd: Đảm bảo không regression các flow hiện hữu]
- [Mục tiêu 3, vd: Đo performance chịu được 1000 user đồng thời]

---

## 3. Phạm vi [bắt buộc]

### 3.1 In-scope (sẽ test)
- [Module / chức năng 1]
- [Module / chức năng 2]
- [Loại testing: functional, regression, performance, security…]
- [Platform: Web Chrome/Safari, iOS, Android, API…]

### 3.2 Out-of-scope (KHÔNG test trong phạm vi này)
- [Hạng mục 1 — lý do: ...]
- [Hạng mục 2 — lý do: ...]

> **Lưu ý**: out-of-scope phải được PM/Tech Lead xác nhận để tránh gap trách nhiệm.

---

## 4. Phân tích rủi ro [bắt buộc]

| ID | Rủi ro | Khả năng (L/M/H) | Tác động (L/M/H) | Mitigation |
|---|---|---|---|---|
| R1 | [vd: Migration data lớn có thể fail giữa chừng] | M | H | Test migration trên clone production, có rollback script |
| R2 | [vd: Tích hợp 3rd party payment chưa stable] | H | H | Mock 3rd party + sandbox test, có fallback flow |
| R3 | [...] | | | |

→ Test case cho rủi ro **(L,L) và (L,M)** có thể giảm; rủi ro **H** bắt buộc cover.

---

## 5. Loại testing & Cách tiếp cận [bắt buộc]

| Loại | Có làm? | Tool | Chủ trì | Ghi chú |
|---|---|---|---|---|
| Functional (UI) | ✅ | Manual + Playwright | QA team | |
| API testing | ✅ | Postman + Newman | QA team | |
| Database testing | ✅ | DBeaver + SQL script | QA team | |
| Integration | ✅ | Postman | QA team | |
| Regression | ✅ | Automation suite | QA + CI | |
| Performance | ✅/❌ | k6 | QA Performance | [Có nếu critical] |
| Security | ✅/❌ | OWASP ZAP | QA Security / 3rd party | [Tùy mức độ] |
| Accessibility | ✅/❌ | axe + manual | QA + UX | [Public-facing có] |
| Compatibility | ✅ | BrowserStack | QA team | |
| Mobile | ✅/❌ | Real device + Appium | QA Mobile | |

---

## 6. Test deliverables [bắt buộc]

- Test Plan (tài liệu này)
- Test Case (Excel / TestRail / Jira link)
- Test Data (nếu có)
- Daily Test Status Report
- Bug Report (Jira)
- Test Summary Report (cuối phase)
- Traceability Matrix

---

## 7. Môi trường test [bắt buộc]

| Môi trường | URL / Build | Mục đích | Owner |
|---|---|---|---|
| DEV | [...] | Smoke từ dev | Dev team |
| QA / Test | [...] | Test chính | QA team |
| Staging / UAT | [...] | UAT, performance | PM + QA |
| Production | [...] | Smoke sau release | QA + DevOps |

### Test data
- [Nguồn: seed script / clone production masked / fixture file]
- [Account test: liệt kê role, credential lưu trong vault — không trong file này]

---

## 8. Tiêu chí Entry & Exit [bắt buộc]

### 8.1 Entry criteria (đủ điều kiện bắt đầu test)
- [ ] Code đã merge vào branch test
- [ ] Build deploy thành công lên QA env
- [ ] Smoke test pass
- [ ] Test case đã review & approve
- [ ] Test data đã ready
- [ ] Document AC đã ổn định (không change > 20% sau khi bắt đầu test)

### 8.2 Exit criteria (đủ điều kiện kết thúc test)
- [ ] 100% test case Critical & High đã thực thi
- [ ] ≥ 95% test case pass
- [ ] Không còn defect Critical/High open
- [ ] Defect Medium open ≤ [N] (theo thỏa thuận)
- [ ] Test Summary Report được approve

### 8.3 Suspension criteria (tạm dừng test)
- Build mới không pass smoke → trả lại dev
- > 50% test case bị blocked do 1 bug Critical → fix bug rồi mới tiếp tục
- Môi trường không ổn định > 2h liên tiếp

---

## 9. Schedule [bắt buộc]

| Phase | Bắt đầu | Kết thúc | Owner | Deliverable |
|---|---|---|---|---|
| Test planning | YYYY-MM-DD | YYYY-MM-DD | QA Lead | Test Plan |
| Test design | YYYY-MM-DD | YYYY-MM-DD | QA team | Test cases |
| Test execution — Cycle 1 | YYYY-MM-DD | YYYY-MM-DD | QA team | Bug list |
| Retest + Cycle 2 | YYYY-MM-DD | YYYY-MM-DD | QA team | Bug list |
| Regression | YYYY-MM-DD | YYYY-MM-DD | QA team | Regression report |
| UAT support | YYYY-MM-DD | YYYY-MM-DD | QA + Business | UAT sign-off |
| Test closure | YYYY-MM-DD | YYYY-MM-DD | QA Lead | Test Summary Report |

---

## 10. Resources [bắt buộc]

### 10.1 Team
| Vai trò | Tên | % effort | Trách nhiệm |
|---|---|---|---|
| QA Lead | [...] | 100% | Plan, review, sign-off |
| QA Engineer | [...] | 100% | Design, execute, report |
| Automation Engineer | [...] | 50% | Automation suite |
| Performance Engineer | [...] | 30% | Load test |

### 10.2 Tool & License
- [Liệt kê tool cần, license đã có hay cần mua]

---

## 11. Communication & Reporting [bắt buộc]

- **Daily standup**: 15 phút mỗi sáng — QA report progress + blocker.
- **Daily Test Status**: gửi cuối ngày — số case run/pass/fail/blocked, top bug.
- **Weekly summary**: gửi PM, Tech Lead.
- **Defect triage**: 2 lần/tuần với dev + PM.
- **Channel**: Slack #project-qa, email cho deliverable chính thức.

---

## 12. Defect Management [bắt buộc]

### Severity (mức độ ảnh hưởng kỹ thuật)
- **S1 — Critical**: crash, data loss, blocker không có workaround.
- **S2 — High**: chức năng chính sai, có workaround khó.
- **S3 — Medium**: chức năng phụ sai, có workaround dễ.
- **S4 — Low**: cosmetic, typo, suggestion.

### Priority (mức độ ưu tiên fix — do PM quyết)
- **P1**: fix ngay, không release nếu chưa fix.
- **P2**: fix trong release này.
- **P3**: fix release sau.
- **P4**: backlog.

### SLA fix
| Severity | SLA fix |
|---|---|
| S1 | 4 giờ |
| S2 | 1 ngày làm việc |
| S3 | 3 ngày |
| S4 | Best effort |

### Tool tracking
- Jira project [...] — board QA-Bug.

---

## 13. Assumptions & Dependencies [bắt buộc]

### Assumptions
- [vd: Requirement không thay đổi > 20% sau khi bắt đầu test]
- [vd: Môi trường QA available 24/7]
- [vd: Dev có đủ unit test trước khi merge]

### Dependencies
- [vd: API service X từ team Y — cần ready trước YYYY-MM-DD]
- [vd: License BrowserStack 5 user — cần renew trước YYYY-MM-DD]

---

## 14. Approvals [bắt buộc]

| Vai trò | Tên | Chữ ký | Ngày |
|---|---|---|---|
| QA Lead | | | |
| Tech Lead | | | |
| Project Manager | | | |
| Product Owner | | | |
