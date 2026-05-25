# Change/Reporting Examples

## Example 1 — V-A Change request (good vs bad)

### Bad

> ### CR-001: Extend timeline
>
> The team needs more time. We should extend by 2 weeks.
>
> Impact: positive — better quality
>
> Risks: low
>
> Approval: please approve

Problems: no problem statement with evidence, no alternatives considered, vague impact analysis (only one dimension, only positive), no specific risks with mitigation, no approval criteria, no cost-of-rejection analysis.

### Better

> ### CR-014: Extend Sprint 16 by 1 week (mục đích bổ sung test cho data migration)
>
> | Trường | Giá trị |
> |---|---|
> | Status | Submitted, awaiting approval |
> | Requested by | PM Hoa |
> | Approval authority | Sponsor + Steering Committee (per change-control policy for >1 week impact) |
> | Date submitted | 18/03/2024 |
> | Variant rationale | User requested change request for scope/schedule change |
>
> **Mô tả vấn đề:**
> Đợt QA của Sprint 16 đã phát hiện 3 lỗi data migration nghiêm trọng (orphan records, mất precision của amount, mapping sai cho 2% giao dịch legacy). Cần thêm thời gian để fix và re-test trước UAT. Theo plan hiện tại UAT bắt đầu 25/03 — không khả thi.
>
> **Đề xuất thay đổi:**
> Kéo dài Sprint 16 thêm 1 tuần (đến 01/04 thay vì 25/03). UAT lùi tương ứng (01/04 thay vì 25/03). Go-live target lùi từ 15/04 thành 22/04.
>
> **Các phương án đã cân nhắc:**
>
> | Phương án | Lý do bác bỏ |
> |---|---|
> | A. Không thay đổi (do nothing) | UAT sẽ fail với 3 known bugs; mất uy tín với business users; vẫn phải fix sau, mất thêm thời gian tổng cộng nhiều hơn |
> | B. Giữ timeline, scope giảm (defer migration, launch không có legacy data) | Phá vỡ business case — Finance team đã commit lên CFO rằng cutover hoàn tất |
> | C. Tăng team size để fix nhanh hơn | Onboard người mới trong 1 tuần không thực tế cho domain data migration cụ thể |
> | **D. Kéo dài 1 tuần (đề xuất)** | — |
>
> **Phân tích tác động:**
>
> | Dimension | Nếu approve | Nếu reject (giữ nguyên) |
> |---|---|---|
> | Scope | Không thay đổi scope; chỉ thêm thời gian fix migration | Scope phải giảm (defer legacy migration) hoặc launch với bugs |
> | Schedule | UAT 01/04, Go-live 22/04 (+1 tuần so plan) | Plan giữ nhưng UAT fail → cumulative delay 3-4 tuần khi fix sau |
> | Cost | Chi phí thêm 1 tuần × team size = ~120M VND | Cost-of-reject: nếu launch buggy → estimated 200-500M VND remediation + reputation cost với Finance |
> | Quality | Better — fix bugs trước UAT, không có known issues vào production | Worse — known bugs trong UAT, có thể leak vào production |
>
> **Rủi ro:**
>
> | Rủi ro | Likelihood | Impact | Mitigation |
> |---|---|---|---|
> | Phát hiện thêm bugs trong tuần buffer | Medium | Medium | Tổ chức code review tăng cường; rolling test trong tuần |
> | UAT users không sẵn sàng cho ngày 01/04 (lịch nghỉ lễ) | Low | High | Đã confirm với CS Lead — họ có thể flex; tuần đầu UAT là smoke test |
> | Sponsor không approve do cumulative delays | Medium | High | Surface this is the 2nd CR; total delay 1.5 tuần so original plan; vẫn trong budget envelope |
>
> **Dependencies:**
> - Phụ thuộc: QA team có capacity cho re-test (đã confirm Linh)
> - Phụ thuộc: SAP team có availability 25-31/03 cho migration retest (cần confirm)
> - Phụ thuộc bởi: marketing campaign đã planned cho 22/04 — coordinator cần update
>
> **Tiêu chí phê duyệt:**
> 1. Approval authority confirm cumulative delay vẫn acceptable
> 2. Sponsor confirm budget envelope vẫn ok
> 3. Steering Committee approve UAT lùi 1 tuần
>
> **Đề xuất từ BA:**
> BA recommend **Approve** với điều kiện:
> - Tuần buffer dùng đúng mục đích (chỉ fix migration + retest, không slip scope khác vào)
> - Daily standup report tới PM về progress fix
> - Nếu hết tuần vẫn còn issue → escalate ngay, không tự kéo dài thêm
>
> Rationale: cost-of-reject cao hơn cost-of-approve nhiều; quality benefit là lớn; rủi ro được mitigate.

Notes on what makes this better:
- ✅ Problem statement specific with evidence (3 bugs)
- ✅ 4 alternatives including do-nothing
- ✅ Impact across 4 dimensions, both approve and reject
- ✅ Cost-of-rejection quantified (this is the most-missed element)
- ✅ Risks with likelihood/impact/mitigation
- ✅ Approval criteria explicit
- ✅ BA recommendation labeled, with rationale, with conditions

---

## Example 2 — V-B Status report excerpt

> ### Báo cáo tiến độ — Dự án Tích hợp POS-ERP — Tuần 18/03 - 24/03/2024
>
> **Người chuẩn bị:** BA Nam
> **Distribution:** Sponsor, Steering Committee, PM, Tech Lead
>
> **Trạng thái tổng thể:** 🟡 **Amber**
>
> *Rationale:* Phát hiện 3 bugs migration nghiêm trọng trong sprint hiện tại. CR-014 đã submitted đề xuất kéo dài 1 tuần. Nếu CR approved trước 22/03, plan back on track với 1 tuần delay; nếu reject hoặc chậm — risk Red.
>
> **Tóm tắt tuần:**
> Tuần này tập trung vào QA migration. Phát hiện sớm 3 bugs critical là tín hiệu tích cực (đã catch trước UAT). CR-014 submitted để xin thêm thời gian fix. Đợi approval.
>
> **Milestone vs plan:**
>
> | Milestone | Plan | Forecast | Status | Ghi chú |
> |---|---|---|---|---|
> | Sprint 16 hoàn tất | 25/03 | 01/04 | 🟡 +1 tuần | Pending CR-014 approval |
> | UAT kickoff | 25/03 | 01/04 | 🟡 +1 tuần | Cascading từ Sprint 16 |
> | Compliance review hoàn tất | 30/03 | 05/04 | 🟡 +1 tuần | Đã thông báo Compliance |
> | Go-live | 15/04 | 22/04 | 🟡 +1 tuần | Cascading; vẫn trong cùng quarter |
>
> **Đạt được trong tuần:**
> - Hoàn tất QA migration full pass, phát hiện 3 bugs critical (chi tiết phụ lục)
> - Bug fix architecture review với Tech Lead — agreed on approach, không cần re-architect
> - Compliance team confirmed sẵn sàng review từ 02/04 (lùi 1 tuần khả thi)
> - Marketing team đã được thông báo về khả năng lùi go-live; chờ confirm chính thức
>
> **Vấn đề và rủi ro:**
>
> | ID | Vấn đề | Owner | Status | Impact nếu không giải quyết | Mitigation |
> |---|---|---|---|---|---|
> | R-014-1 | 3 bugs migration critical | Tech Lead | Open, fix in progress | Block UAT, block go-live | CR-014 submitted; daily standup tracking |
> | R-014-2 | UAT users (CS team) có lịch nghỉ lễ 30-31/03 | BA | Resolved | Nếu UAT lùi 01/04, một số members chưa sẵn sàng | Đã flex schedule với CS Lead — tuần đầu UAT là smoke test, full test tuần 2 |
> | R-014-3 | Marketing campaign 22/04 — chưa lock thay đổi | PM | Open | Nếu go-live lùi mà marketing không update → mismatch | Marketing đã informally aware; chờ CR-014 approve để chính thức re-plan |
>
> **Vướng mắc cần leadership:**
> - **Cần quyết định:** CR-014 (kéo dài 1 tuần). Sponsor + Steering Committee approval cần trước 22/03 để tránh cascading delays.
>
> **Quyết định cần thiết tuần này:**
> 1. Approve / reject / approve-with-conditions cho CR-014
> 2. Nếu approve: confirm cumulative delay vẫn acceptable trong scope quarterly OKR
>
> **Kế hoạch tuần tới (25/03 - 31/03):**
> - Fix 3 bugs migration (assumed CR-014 approved)
> - Re-test migration full pass
> - Prep UAT environment và test data
> - Brief UAT testers
> - Compliance pre-check (chuẩn bị tài liệu)

Notes:
- ✅ RAG with rationale, not just color
- ✅ Milestone planned vs forecast vs status
- ✅ Issues table with full info
- ✅ Blockers explicit, decisions needed explicit
- ✅ Forward look section
- ✅ No sandbagging — surface CR-014 clearly

---

## Example 3 — V-C Gap analysis excerpt

> ### Phân tích khoảng cách — Năng lực kiểm thử tự động
>
> **Scope:** Đánh giá năng lực QA automation hiện tại của team Engineering vs target state để support 10 deployment/tuần.
>
> **Target state (định nghĩa):**
> - 80%+ regression tests automated
> - Smoke test chạy trong < 10 phút khi commit
> - Daily run on staging, weekly run on production-mirror
> - Defect leak rate < 5% (defects found in production / total defects)
> - QA team có 30%+ thời gian dành cho exploratory + new-feature test
>
> **Current state (evidence-based):**
>
> | Aspect | Today | Source |
> |---|---|---|
> | % regression automated | ~20% | Test management tool report 03/2024 |
> | Smoke test runtime | ~45 phút (lúc nào chạy được) | Build logs |
> | Run frequency | Ad-hoc, không scheduled | QA Lead interview |
> | Defect leak rate (last quarter) | 18% | Defect tracking system |
> | QA team time on exploratory | ~10% | QA team timesheet review |
>
> **Gap table:**
>
> | # | Dimension | Current | Target | Gap size | Priority | Root cause hypothesis |
> |---|---|---|---|---|---|---|
> | G-01 | Test automation coverage | 20% | 80% | Large | P0 | Lack of dedicated automation engineer; team treats automation as side-task |
> | G-02 | Smoke test speed | 45 phút | <10 phút | Medium | P0 | Tests run serially; nhiều redundant tests; no parallelization infrastructure |
> | G-03 | CI/CD integration | Ad-hoc | Daily + on-commit | Large | P1 | CI pipeline không có test integration step; manual triggering |
> | G-04 | Production-mirror environment | None | Available | Large | P1 | Infrastructure cost + maintenance; never prioritized |
> | G-05 | Defect leak rate | 18% | <5% | Large | P0 | Direct outcome of G-01, G-02, G-03 — addressing those should improve leak rate |
> | G-06 | Exploratory test time | 10% | 30% | Medium | P2 | Outcome of automation freeing manual capacity — improve after G-01 done |
>
> **Recommended actions:**
>
> | # | Hành động | Addresses | Effort | Dependencies | Expected outcome | Owner |
> |---|---|---|---|---|---|---|
> | R-01 | Hire 2 automation engineers | G-01, G-02 | L | Budget approval | Bandwidth to build automation infra | Engineering Manager |
> | R-02 | Audit existing 200+ regression tests, deprecate 30% redundant | G-02 | M | None | Faster suite, easier maintenance | QA Lead + automation engineers (post R-01) |
> | R-03 | Add test execution to CI pipeline (block merge on failure) | G-03 | M | R-01 partial | Tests run on every commit | DevOps + automation engineers |
> | R-04 | Set up production-mirror env using IaC | G-04 | L | Cloud budget | Realistic testing environment | DevOps |
> | R-05 | Migrate top 100 critical paths to automated tests in next 6 months | G-01 | L | R-01 fully onboarded | Reach ~50% automation coverage | Automation engineers |
> | R-06 | Establish defect-leak monthly review cadence | G-05 | XS | None | Visibility + accountability | QA Lead |
>
> **Sequencing recommendation:**
> Q2: R-01 (hire), R-06 (cadence)
> Q3: R-02 (audit), R-04 (production mirror), R-03 (CI integration)
> Q4 - Q1 next year: R-05 (migration to automated)
>
> Reach target state ~12-15 months realistic.
>
> **Rủi ro nếu không close gaps:**
> - Defect leak rate 18% — at current production volume = ~3-5 customer-facing defects/tháng → reputation + support cost
> - Sprint velocity affected — team dành thời gian manual regression thay vì new feature
> - Compliance audit risk — không có repeatable test evidence
>
> **BA's recommendation:**
> Phân bổ ngân sách Q2 cho R-01 + R-04 (cost ~700M VND/year fully loaded for 2 engineers + infrastructure). ROI break-even khoảng 18 tháng dựa trên giảm defect leak + reclaim velocity. Đề xuất ưu tiên cao do compounding nature — mỗi tháng delay = thêm tech debt + missed productivity.

Notes:
- ✅ Target state defined first, with measurable criteria
- ✅ Current state evidence-based with sources
- ✅ Gaps prioritized with rationale
- ✅ Each recommendation maps to specific gaps
- ✅ Sequencing realistic
- ✅ Risks of inaction stated
- ✅ BA recommendation with financial framing

---

## Example 4 — V-D SWOT with TOWS

> ### Phân tích SWOT — Sản phẩm Ví điện tử (Q2 2024, 12 tháng tới)
>
> **Scope:** Sản phẩm ví điện tử của công ty (B2C retail)
> **Time horizon:** Q2 2024 - Q1 2025
>
> **Strengths (Điểm mạnh — internal):**
> - S1: User base 1M active (top 5 thị trường VN per industry report 02/2024)
> - S2: Engineering team 50 người, có domain expertise sâu (avg tenure 3.2 năm)
> - S3: Tỷ lệ KYC pass rate cao (92%) so industry avg (~85%) — UX onboarding tốt
> - S4: Cash flow positive từ Q4 2023 — không phụ thuộc funding round
>
> **Weaknesses (Điểm yếu — internal):**
> - W1: Defect leak rate cao (18%) — chất lượng cần cải thiện (xem gap analysis G-05)
> - W2: Product team chỉ có 3 PM cho 4 product lines — bandwidth constraint
> - W3: Customer Service phụ thuộc nhiều vào manual processes — ticket volume tăng nhanh hơn capacity
> - W4: Marketing brand awareness ngoài Hà Nội/HCMC còn thấp (per Q1 brand survey: 23% aided awareness ở tier 2 cities)
>
> **Opportunities (Cơ hội — external):**
> - O1: SBV mới ban hành thông tư khuyến khích thanh toán không tiền mặt — tailwind regulatory
> - O2: Thị trường tier 2 cities tăng adoption smartphone + internet (penetration +15% YoY)
> - O3: Đối thủ chính (Competitor X) đang gặp khủng hoảng compliance — cơ hội grab market share
> - O4: Mở rộng sang B2B SME segment — chưa có incumbent rõ ràng trong segment này
>
> **Threats (Thách thức — external):**
> - T1: 2 startups mới (Y, Z) được funded $50M+ trong 6 tháng — sẽ cạnh tranh aggressive
> - T2: Quy định mới về data residency có thể yêu cầu thay đổi infrastructure (cost estimate ~5-10B VND)
> - T3: Tỷ giá USD/VND biến động — chi phí cloud (USD-billed) tăng
> - T4: Macro economic uncertainty 2024 — consumer spending có thể giảm
>
> **Strategic implications — TOWS:**
>
> **SO strategies (dùng Strength để capture Opportunity):**
> - **SO-1:** Dùng S1 (user base 1M) + S3 (UX onboarding tốt) → O3 (Competitor X khủng hoảng): chạy aggressive acquisition campaign target users Competitor X, leverage onboarding speed
> - **SO-2:** Dùng S2 (engineering team) + S4 (cash flow positive) → O4 (B2B SME): tự đầu tư phát triển B2B SME product không cần raise fund
>
> **WO strategies (fix Weakness để enable Opportunity):**
> - **WO-1:** Fix W1 (defect leak) → required cho O3: nếu push acquisition, chất lượng phải improve trước, otherwise churn cao
> - **WO-2:** Fix W4 (tier 2 brand awareness) → enable O2 (tier 2 market): cần invest brand campaign tier 2 trước expansion
>
> **ST strategies (dùng Strength để defend Threat):**
> - **ST-1:** Dùng S4 (cash flow) → T1 (well-funded startups): không cần raise fund nhưng phải allocate margin cho R&D + marketing để giữ lead, không sit on profitability
> - **ST-2:** Dùng S2 (engineering team) → T2 (data residency): có capability tự thiết kế và migrate infrastructure nếu quy định ban hành
>
> **WT strategies (avoid/mitigate Weakness + Threat):**
> - **WT-1:** W2 (PM bandwidth) + T1 (new competitors): nguy cơ slow product velocity vào lúc cần fast response → ưu tiên hire 2 PMs Q2
> - **WT-2:** W3 (CS manual) + T4 (macro) + scale acquisition: nếu acquisition push thành công, CS sẽ quá tải → invest CS automation trước scale
>
> **Top 5 chiến lược ưu tiên (BA recommendation):**
>
> 1. **Q2: Improve quality foundation** (WO-1) — fix defect leak rate trước khi scale acquisition. Đây là enabler cho mọi thứ khác.
> 2. **Q2-Q3: Aggressive acquisition target Competitor X** (SO-1) — cơ hội time-sensitive; competitor sẽ recover hoặc bị thay thế.
> 3. **Q3: Hire 2 PMs + invest CS automation** (WT-1, WT-2) — chuẩn bị infrastructure cho scale.
> 4. **Q3-Q4: Tier 2 brand campaign + expansion** (WO-2, O2) — sau khi quality foundation vững.
> 5. **Q4: Explore B2B SME** (SO-2) — diversification play khi consumer market có thể slow do T4.
>
> **Khuyến nghị từ BA:**
> Ưu tiên #1 và #2 — cộng hưởng (quality enables acquisition). Defer B2B SME (#5) nếu Q2-Q3 không deliver kết quả mong đợi; tập trung resources giữ lead consumer.

Notes:
- ✅ Items specific with evidence (numbers, sources)
- ✅ Internal/external classification correct
- ✅ TOWS combinations produce strategic insights, not just listing
- ✅ Top recommendations derived from analysis, not pre-decided
- ✅ Sequencing reasoning provided
- ✅ BA recommendation clearly labeled
