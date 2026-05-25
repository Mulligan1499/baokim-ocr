# Test Summary Report — [Tên Release / Sprint]

> Báo cáo tổng kết phase test, gửi cho PM / Tech Lead / stakeholder để quyết định **release readiness**.
> Copy file, điền data, xóa hướng dẫn `>` trước khi gửi.

---

## 1. Thông tin chung

| Hạng mục | Giá trị |
|---|---|
| **Release / Sprint** | [Release v2.5 / Sprint 12] |
| **Phạm vi** | [Liệt kê feature chính] |
| **Test cycle** | YYYY-MM-DD đến YYYY-MM-DD |
| **Tác giả báo cáo** | [QA Lead] |
| **Ngày báo cáo** | YYYY-MM-DD |
| **Build cuối cùng được test** | [Build ID + commit hash] |

---

## 2. Executive Summary (1 đoạn ngắn cho stakeholder)

> Kết luận chính trong 3-5 câu, đặt **lên đầu** vì PM thường chỉ đọc phần này.

[Ví dụ:
"Release v2.5 đã hoàn thành test với 245/250 test case pass (98%). Phát hiện và fix 38 bug, trong đó có 2 bug Critical liên quan đến payment gateway timeout đã được resolve và verify. Hiện còn 3 bug Medium open (P3) đã được defer sang sprint sau với PM approval. **Đề xuất: GO cho release**, kèm theo monitoring tăng cường vùng payment trong 48h đầu sau go-live."]

**Recommendation: ✅ GO / ⚠️ GO with conditions / ❌ NO-GO**

---

## 3. Test Coverage

### 3.1 Coverage theo requirement

| Module | Requirement | TC planned | TC executed | Coverage |
|---|---|---|---|---|
| Login | 5 | 25 | 25 | 100% |
| Checkout | 8 | 60 | 58 | 96.7% |
| Order Mgmt | 6 | 40 | 40 | 100% |
| Payment | 4 | 35 | 35 | 100% |
| Search | 3 | 20 | 18 | 90% |
| API | 12 endpoints | 60 | 60 | 100% |
| **Total** | **38** | **240** | **236** | **98.3%** |

### 3.2 Coverage theo loại test

| Loại | Có thực hiện? | Coverage / Note |
|---|---|---|
| Functional | ✅ | 100% AC covered |
| Negative / Edge | ✅ | BVA, EP applied |
| Regression | ✅ | Full automation suite + manual smoke |
| Integration | ✅ | All 12 API endpoints |
| Database | ✅ | Migration verified, integrity check pass |
| Performance | ✅ | Load test với 1000 concurrent — pass SLA |
| Security | ⚠️ | OWASP ZAP scan pass; pen-test scheduled cho Q2 |
| Accessibility | ✅ | WCAG AA verified với axe + manual NVDA |
| Cross-browser | ✅ | Chrome, Safari, Firefox, Edge — pass |
| Mobile (responsive) | ✅ | iPhone SE → Pro Max, Pixel, Galaxy — pass |
| Localization | N/A | Single language (vi-VN) |

### 3.3 Test cases — chi tiết

| Status | Count | % |
|---|---|---|
| **Passed** | 230 | 97.5% |
| **Failed** | 4 | 1.7% |
| **Blocked** | 2 | 0.8% |
| **Not Run** | 4 | (do AC change cuối, defer sang sprint sau) |
| **Total executed** | 236 / 240 | 98.3% |

**Failed test cases** (sau retest cuối cùng):
- TC_CHECKOUT_058 — vẫn fail, link bug PROJ-1234 — defer (S3, P3)
- TC_SEARCH_018 — fail edge case Unicode RTL, link PROJ-1240 — defer
- TC_API_045 — performance dưới SLA cho payload lớn, link PROJ-1255 — fix sprint sau
- TC_DB_012 — data integrity rule mới, link PROJ-1260 — defer

---

## 4. Defect Summary

### 4.1 Tổng quan

| | Count |
|---|---|
| Total bug logged | 38 |
| Fixed & verified | 33 |
| Deferred (PM approved) | 3 |
| Reopened | 2 (đã re-fix và verify) |
| Open at release | 3 (S3 trở xuống) |

### 4.2 Theo Severity

| Severity | Logged | Fixed | Open at release |
|---|---|---|---|
| S1 Critical | 2 | 2 | 0 |
| S2 High | 8 | 8 | 0 |
| S3 Medium | 18 | 17 | 1 |
| S4 Low | 10 | 6 | 2 |

### 4.3 Theo Module

| Module | Count |
|---|---|
| Checkout | 12 |
| Payment | 8 |
| Search | 6 |
| Login | 4 |
| Order Mgmt | 5 |
| API | 3 |

→ **Module có defect density cao cần focus regression sau release**: Checkout (12/60 TC = 20%), Payment (8/35 TC = 23%).

### 4.4 Defect leakage từ pha trước (nếu có)

| Pha phát hiện | Count | Nguyên nhân |
|---|---|---|
| UAT | 2 | AC chưa đề cập tới case enterprise user |
| Production (release trước) | 1 | Edge case timezone không cover |

→ **Action**: bổ sung test case cho timezone trong regression suite. Cải tiến quy trình review AC với BA.

---

## 5. Critical Issues & Open Risks

### 5.1 Bug đang open khi release

| Bug ID | Title | Severity | Priority | Workaround | Plan |
|---|---|---|---|---|---|
| PROJ-1234 | Sort theo tên không đúng cho ký tự đặc biệt | S3 | P3 | Sort theo cột khác | Sprint 13 |
| PROJ-1240 | Search RTL Unicode hiển thị sai layout | S3 | P3 | Không gặp ở user thực | Sprint 13 |
| PROJ-1245 | Tooltip không hiển thị trên IE11 | S4 | P4 | IE11 không support | Backlog |

### 5.2 Risk còn lại

| Risk | Mitigation |
|---|---|
| Payment gateway có thể slow vào giờ peak | Monitoring + alert; fallback message rõ |
| Migration 100M record có thể chậm | Đã test trên clone production — 28 phút, acceptable; có rollback |
| Cache invalidation cho catalog | Đã test — verify logic; monitor cache hit rate |

---

## 6. Performance Test Summary (nếu có)

| Scenario | Target | Actual | Pass? |
|---|---|---|---|
| Login API — P95 | < 500ms | 320ms | ✅ |
| Checkout API — P95 | < 1s | 780ms | ✅ |
| Search API — P95 | < 800ms | 720ms | ✅ |
| 1000 concurrent — error rate | < 1% | 0.3% | ✅ |
| 24h soak test | No memory leak | Memory stable | ✅ |
| Spike 5x normal | Recover < 30s | Recovered 18s | ✅ |

---

## 7. Security Test Summary (nếu có)

| Hạng mục | Result |
|---|---|
| OWASP ZAP scan | 0 High, 2 Medium (false positive verified), 5 Low |
| Dependency vulnerability scan | 0 Critical, 1 High (đã update lib) |
| Manual pen-test (key flow) | Pass — không tìm thấy injection, IDOR, auth bypass |
| TLS configuration (SSL Labs) | A+ |
| Secret scan trên repo | Clean |

---

## 8. Lessons Learned

### 8.1 Things that went well
- [vd: Automation regression suite chạy 30 phút mỗi night → catch sớm 4 regression bug]
- [vd: Daily triage giúp clear bug nhanh, không bị backlog dồn]

### 8.2 Things to improve
- [vd: AC review chưa kỹ → 5 bug sinh ra do hiểu nhầm AC. Đề xuất: 3-amigos meeting (BA + Dev + QA) cho mỗi story P0/P1]
- [vd: Performance test bắt đầu muộn → 2 bug perf phát hiện cận release. Đề xuất: bake vào pipeline CI từ sprint 1]
- [vd: Test data setup tốn 30% effort. Đề xuất: build seed script reusable]

### 8.3 Action items cho sprint sau
| Action | Owner | Due |
|---|---|---|
| Setup performance test trong CI | QA Perf + DevOps | Sprint 13 W1 |
| Build seed script standard | QA Lead | Sprint 13 W2 |
| 3-amigos meeting cho story P0/P1 | PM | From sprint 13 |

---

## 9. Test Effort

| Hạng mục | Planned (h) | Actual (h) | Variance |
|---|---|---|---|
| Test design | 40 | 45 | +12% |
| Test execution | 120 | 135 | +12% |
| Bug verify / regression | 30 | 38 | +27% |
| Reporting / meeting | 20 | 22 | +10% |
| **Total** | **210** | **240** | **+14%** |

→ **Variance giải thích**: 2 round retest do regression bug từ fix lần 1.

---

## 10. Sign-off

| Vai trò | Tên | Quyết định | Chữ ký | Ngày |
|---|---|---|---|---|
| QA Lead | | GO / NO-GO | | |
| Tech Lead | | | | |
| Product Owner | | | | |
| Project Manager | | | | |

---

## 11. Phụ lục

- A. Test Plan link
- B. Test cases link (TestRail / Excel)
- C. Bug list export
- D. Performance test report (full)
- E. Security scan report (full)
- F. Traceability matrix
