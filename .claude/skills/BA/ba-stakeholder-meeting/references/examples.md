# Stakeholder/Meeting Examples

## Example 1 — V-A: Stakeholder analysis excerpt

### Bad

> Stakeholders:
> - John (PM): wants project to succeed
> - Mary (Finance): controls budget
> - Dev team: builds the thing
> - Users: use it

Problems: personal names without permission, generic interests, no power/interest assessment, no engagement strategy.

### Better

**Stakeholders + assessment:**

| Stakeholder (role) | Power | Interest | Quadrant | Business interest | Engagement strategy |
|---|---|---|---|---|---|
| Executive Sponsor (CFO) | High | High | Manage closely | Project achieves stated ROI of 30% cost reduction; aligns with FY24 strategic priorities; avoids reputational risk | Monthly 1:1 with PM + BA; ad-hoc when major decisions needed; quarterly written status with risks |
| Compliance Director | High | Medium | Keep satisfied | All regulatory requirements met for KYC; audit trail intact; data residency compliant | Brief at start of each phase; ad-hoc consultation on compliance touch points; written sign-off before go-live |
| Engineering Manager | Medium | High | Manage closely | Team capacity isn't over-committed; tech debt manageable; architecture aligns with platform direction | Weekly working session; included in design review |
| Customer Service Lead | Medium | High | Manage closely | Process changes don't increase ticket volume; team trained before go-live; clear escalation path | Bi-weekly check-in; included in UAT; comprehensive training plan delivered 2 weeks before go-live |
| End customers | Low | Variable | Keep informed | New experience is intuitive; doesn't disrupt their habits suddenly | In-app announcement 1 week before launch; help center content; CS prepared for FAQ |
| Marketing Team | Low | Medium | Keep informed | Launch timing aligns with their campaign calendar; can prepare materials | Monthly update; final launch date confirmed 4 weeks before |
| Internal Audit | High | Low | Keep satisfied | Project doesn't create audit findings; documentation complete and accessible | Brief at planning stage; provide final documentation pack; respond to ad-hoc questions |

Notes on what makes this better:
- ✅ Roles, not personal names (could add names if user explicitly provided)
- ✅ Power/interest assessment with quadrant assignment
- ✅ Business interest specific, not generic ("achieve ROI", not "wants project success")
- ✅ Engagement strategy concrete (cadence + channel + content)
- ✅ Includes often-forgotten stakeholders (Compliance, Internal Audit)

---

## Example 2 — V-B: RACI excerpt

### Bad

| Activity | PM | BA | Dev | QA | Sponsor |
|---|---|---|---|---|---|
| Plan | RA | RA | I | I | C |
| Build | I | I | RA | I | I |
| Test | I | I | C | RA | I |
| Launch | RA | C | R | R | C |

Problems: multiple A on "Plan" (RA RA), "RA" used everywhere without thought, no decision rules, generic activities.

### Better

| Activity | Sponsor | PM | BA | Tech Lead | QA Lead | Compliance | Customer Service |
|---|---|---|---|---|---|---|---|
| Define scope and success criteria | A | R | C | C | I | C | I |
| Approve scope | A | R | C | I | I | C | I |
| Define functional spec | I | C | RA | C | I | C | C |
| Approve functional spec | A | C | R | C | C | C | I |
| Approve architecture | I | I | I | RA | C | C | I |
| Build and unit test | I | I | C | RA | C | I | I |
| Sign off on UAT | A | R | R | C | RA | C | C |
| Approve go-live | RA | R | C | C | C | C | I |
| Manage incident post-launch | I | RA | C | R | C | C | R |
| Communicate to customers | C | C | R | I | I | C | RA |
| Decide on scope-change in-flight | A | R | C | C | I | C | I |

### Decision rules

- **Scope change rule:** Any change increasing budget by >10% or schedule by >2 weeks requires Sponsor approval. Smaller changes can be approved by PM with BA documentation.
- **Compliance veto:** Compliance has veto power on any decision affecting regulatory requirements. Disagreement escalates to Sponsor.
- **Architecture veto:** Tech Lead can block decisions that introduce critical tech debt; escalation path: Tech Lead → Engineering Manager → Sponsor.
- **UAT failure:** If UAT identifies blockers, Tech Lead + QA Lead jointly assess; QA Lead recommends, PM decides on extending/re-scheduling.

Notes:
- ✅ One A per activity (Sponsor accountable for scope and go-live, PM for incidents, etc.)
- ✅ Specific, actionable activities (not just "Plan")
- ✅ C and I used distinctly (Compliance C on scope = consulted before; I would mean told after)
- ✅ Decision rules surface escalation and veto rights

---

## Example 3 — V-C: Meeting minutes (good vs bad)

### Bad

> Meeting on Tuesday
> Attendees: everyone
>
> We discussed the project. People had concerns. We need to figure out the database. Mary will look into it. Talked about timeline.
>
> Next meeting: TBD

Problems: no date, no specific attendees, no decisions extracted, no clear action items, no parking lot.

### Better

> ### Biên bản họp — Sprint Planning #14
>
> **Ngày / giờ:** 12/03/2024 14:00 - 15:30
> **Địa điểm:** Zoom (recording lưu tại link X)
> **Người ghi:** BA Nam
>
> **Tham dự:**
> - PM Hoa
> - BA Nam (recorder)
> - Tech Lead Khang
> - QA Lead Linh
> - Designer An
>
> **Vắng mặt:**
> - Sponsor Hùng (đã briefed sau)
>
> **Agenda:**
> 1. Review sprint 13 output
> 2. Plan sprint 14
> 3. Decide on database migration approach
> 4. Discuss compliance review timing
>
> **Quyết định:**
>
> | ID | Quyết định | Owner | Ngày | Rationale | Dissent | Hiệu lực |
> |---|---|---|---|---|---|---|
> | D-014-1 | Sprint 14 scope: chỉ feature A và B, deferred feature C | PM Hoa | 12/03 | Dev team capacity limited do 1 thành viên nghỉ phép tuần đầu | Linh đề nghị giữ C — overruled vì rủi ro quality | Bắt đầu sprint 14 |
> | D-014-2 | Database migration approach: incremental (3 phases) thay vì big-bang | Tech Lead Khang | 12/03 | Giảm rủi ro rollback; phù hợp downtime tolerance của Ops | Không có | Áp dụng từ Sprint 15 |
> | D-014-3 | Compliance review sẽ chèn vào cuối Sprint 16 (1 tuần buffer trước UAT) | PM Hoa | 12/03 | Tránh review-fail làm trễ UAT | Không có | Lên lịch ngay |
>
> **Action items:**
>
> | ID | Việc | Owner | Deadline | Status |
> |---|---|---|---|---|
> | A-014-1 | Cập nhật sprint board reflect D-014-1 | PM Hoa | 12/03 EOD | Open |
> | A-014-2 | Chia migration plan thành 3 phases với specific risk per phase | Tech Lead Khang | 15/03 | Open |
> | A-014-3 | Đặt lịch Compliance review meeting cuối Sprint 16 | BA Nam | 14/03 | Open |
> | A-014-4 | Brief Sponsor Hùng về D-014-1, D-014-2, D-014-3 | PM Hoa | 13/03 | Open |
>
> **Discussion notes (synthesized, không verbatim):**
> - Đội thảo luận về việc liệu deferred feature C có thể split thành sub-features để giữ một phần trong Sprint 14. Khang đề xuất kỹ thuật là khả thi nhưng rủi ro test coverage tăng. PM Hoa quyết định defer toàn bộ để giữ chất lượng.
> - Linh nêu lo ngại về việc test coverage của migration trong incremental approach — D-014-2 vẫn tiến nhưng test plan riêng được phân tích chi tiết tại sprint kế.
> - An đề nghị cần Designer involvement sớm hơn trong design review — agreed; sẽ thử mới từ Sprint 15.
>
> **Parking lot:**
> - Refactoring tech debt trong module X — chưa cấp bách, revisit Sprint 17 review
> - Đề xuất automation framework upgrade — Khang sẽ explore offline và present Sprint 16
> - Customer feedback channel cho beta users — Designer An sẽ work với Marketing, revisit Sprint 15
>
> **Next meeting:** Sprint planning #15, 26/03/2024 14:00

Notes:
- ✅ Specific date, time, attendees with roles
- ✅ Decisions distinct from discussion, each with owner + rationale + dissent
- ✅ Action items with owner + deadline + status
- ✅ Discussion synthesized (not verbatim), neutral language
- ✅ Parking lot has revisit trigger
- ✅ Next meeting scheduled

---

## Example 4 — V-D: Communication plan excerpt

> ### Kế hoạch giao tiếp — Dự án "Tích hợp POS-ERP"
>
> | Stakeholder | Kênh chính | Tần suất | Nội dung trọng tâm | Owner |
> |---|---|---|---|---|
> | Sponsor (CFO) | 1:1 + báo cáo viết | 1:1 hàng tháng (30 phút), báo cáo viết hàng tháng | Tiến độ chiến lược, rủi ro, quyết định cần thiết, ROI projections | PM |
> | Steering Committee | Họp + slide deck | Quý/lần | Milestone review, decisions cần thiết ở committee level | PM + BA |
> | Engineering Manager | 1:1 + Slack | 1:1 mỗi 2 tuần, Slack hàng ngày | Capacity, tech debt, escalations | Tech Lead |
> | Dev team | Standup + Slack | Daily standup, async Slack 24/7 | Execution detail, blockers, code reviews | Tech Lead |
> | QA team | Working session + email | Hằng tuần meeting, email cho từng release | Test plan progress, defect trends, sign-off criteria | QA Lead |
> | Compliance | Email + checkpoint meeting | Hằng tuần email tóm tắt, mỗi phase 1 meeting chính thức | Compliance touch points, evidence, attestations needed | BA |
> | Finance team (end users) | Demo + email | Demo sau mỗi sprint, email tóm tắt hàng tuần | Functionality progress, UAT prep, training schedule | BA + Product |
> | Store accountants (end users) | Newsletter + training | Newsletter hàng tháng từ Sprint 3, training intensive 2 tuần trước cutover | Process changes, what's coming, support channels | BA + CS |
> | Vendor (POS) | Email + working session | Bi-weekly meeting, email mỗi 2-3 ngày | Technical clarifications, schedule sync | Tech Lead + BA |
>
> ### Crisis / Incident communication
> Khác với cadence steady-state:
> - **Trigger:** sự cố ảnh hưởng > 1 cửa hàng hoặc > 4 giờ
> - **Cadence:** real-time qua kênh dedicated (Slack channel cụ thể), status update mỗi 2 giờ cho stakeholders
> - **Decision:** Tech Lead + PM cùng quyết định mức độ; nếu impact > 50 cửa hàng → escalate Sponsor ngay
> - **Communication to end users:** đi qua CS, không trực tiếp từ project team

Notes:
- ✅ Frequency calibrated to stakeholder (CFO monthly, dev daily, end users when ready)
- ✅ Content focus distinct per stakeholder
- ✅ Owner identified
- ✅ Crisis path documented separately
