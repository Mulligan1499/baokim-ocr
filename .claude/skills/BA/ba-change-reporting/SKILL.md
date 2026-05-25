---
name: ba-change-reporting
description: Produce change requests, status reports, gap analyses, and SWOT analyses. Use whenever the user wants to write a change request, status report, gap analysis between current and target state, or SWOT analysis. Triggers on phrases like "change request", "yêu cầu thay đổi", "CR cho", "status report", "báo cáo tiến độ", "gap analysis", "phân tích khoảng cách", "SWOT", "phân tích SWOT", "assessment report". Produces change requests (problem statement, proposed change, impact analysis across scope / schedule / cost / quality, risks, approval criteria), status reports (RAG status with rationale, progress, blockers, milestones, decisions needed), gap analysis (current state, target state, gap size, prioritized recommendations), or SWOT (Strengths / Weaknesses / Opportunities / Threats with strategic implications via SO/WO/ST/WT combinations). Stays at BA analysis level — surfaces facts, options, and recommendations. Does NOT make the decision itself (leadership decides; BA structures the analysis).
---

# BA Change & Reporting

Produce structured analytical deliverables that BAs use to inform decisions: change requests, status reports, gap analyses, SWOT analyses. The BA's job is to **structure the analysis**, surface options, and give recommendations — leadership decides. This skill never makes the decision itself.

## The BA boundary for change/reporting

**Cover:**
- **Facts** — observable state, measurable progress, known constraints
- **Analysis** — comparing options, weighing trade-offs, identifying patterns
- **Recommendations** — what BA suggests based on analysis, with rationale
- **Decision-supporting structure** — clear criteria for the decider to choose

**Do NOT cross into:**
- **Making the decision** — BA presents; leadership decides. Even when BA has a strong opinion, frame as recommendation, not directive.
- **Speculation beyond evidence** — opinions presented as facts ("the team is unhappy" without source)
- **Editorial / political commentary** — keep neutral, business-focused
- **Confidential information** — compensation, performance, legal under privilege

**Acid test:** could a decider read your output and make an informed choice in either direction (do / don't do, approve / reject)? Yes → right balance. If the output only supports one outcome, you've drifted into advocacy.

That said, **recommendations are expected** — neutral doesn't mean wishy-washy. State the recommendation clearly with rationale; respect the decider's authority to override.

---

## Step 0 — Detect variant

| Variant | Signals | Output |
|---|---|---|
| **V-A — Change request** | "change request", "CR cho", "yêu cầu thay đổi", "đề xuất thay đổi scope/budget/schedule" | Problem statement + proposed change + impact analysis + risks + approval criteria |
| **V-B — Status report** | "status report", "báo cáo tiến độ", "weekly/monthly status", "project update" | RAG status + progress + blockers + decisions needed + upcoming milestones |
| **V-C — Gap analysis** | "gap analysis", "phân tích khoảng cách", "current vs target capability", "assessment" | Current state + target state + prioritized gaps + recommended actions |
| **V-D — SWOT analysis** | "SWOT", "phân tích SWOT", "strategic assessment", "Strengths Weaknesses Opportunities Threats" | SWOT quadrants + strategic implications (SO/WO/ST/WT combinations) |

These variants can compose: gap analysis often feeds into change requests; SWOT often informs strategic gap analysis. But produce them as separate deliverables, not blended.

Record variant + rationale in metadata top.

---

## Workflow

### Step 1 — Read context

Universal minimum:
1. What's the subject? (which project, capability, decision)
2. Who's the audience? (sponsor, steering committee, leadership, external — affects depth and tone)
3. What decision/action is expected after reading?

Variant-specific questions in `references/variants.md`. Ask minimum (max 3). Track for appendix.

**Critical prerequisites.**
- **V-A change request:** prior baseline (the scope/budget/schedule being changed) must exist and be referenced
- **V-B status report:** plan exists with milestones to report against
- **V-C gap analysis:** target state defined (otherwise gap is undefined)
- **V-D SWOT:** scope is defined (what entity is being analyzed — company? product? team? project?)

If any prerequisite missing, flag at top.

### Step 2 — Determine sections

Universal:
- Metadata (variant, variant rationale, scope, owner, status, date, audience)
- Critical prerequisites (if any)
- Variant-specific main content
- BA's recommendation (where applicable — V-A, V-C definitely; V-B optional; V-D yes)
- Open questions / topics for decision
- Assumptions appendix

### Step 3a — V-A: Change request structure

**Sections:**

1. **Change request ID + title** (e.g., CR-014: Extend Sprint 16 by 1 week)
2. **Status** (Draft / Submitted / Under Review / Approved / Rejected / Implemented)
3. **Requested by** (role) + **Approval authority** (per change-control policy, who approves this size of change)
4. **Problem statement** (1-3 sentences — what's wrong / what needs to change, with evidence)
5. **Proposed change** (1 paragraph — specific what)
6. **Alternatives considered** (≥2 alternatives, including "do nothing", with brief why-rejected)
7. **Impact analysis — across 4 dimensions:**

   | Dimension | Impact if approved | Impact if rejected |
   |---|---|---|
   | Scope | What changes about deliverables / features | What stays same / what suffers |
   | Schedule | New target dates; affected milestones | Current dates; what slips anyway |
   | Cost | $ amount or effort estimate; budget category | Cost of not changing (often missed) |
   | Quality | Effect on quality (positive: more time for X; negative: less testing) | Effect on quality if rejected |

8. **Risks** — table:

   | Risk | Likelihood | Impact | Mitigation |
   |---|---|---|---|

9. **Dependencies** — what this change depends on; what depends on this change
10. **Approval criteria** — what reviewers should evaluate (so they have a checklist)
11. **BA's recommendation** — Approve / Approve-with-conditions / Reject, with rationale

**Important framing:** the recommendation is the BA's, clearly labeled as such. Reviewers still decide.

### Step 3b — V-B: Status report structure

**Sections:**

1. **Report metadata** — Project, reporting period, prepared by, distribution list
2. **Overall status** — RAG (Red / Amber / Green) with 1-2 sentence rationale
3. **Period summary** — 2-3 sentence executive summary
4. **Progress vs plan**

   | Milestone | Planned date | Actual / Forecast | Status | Notes |
   |---|---|---|---|---|

5. **Accomplishments this period** — 3-5 bullet points, concrete and observable
6. **Issues and risks**

   | ID | Issue/Risk | Owner | Status | Impact if unresolved | Mitigation |
   |---|---|---|---|---|---|

7. **Blockers requiring leadership attention** — explicit list of decisions/escalations needed (if none: state "None this period")
8. **Decisions needed** — what specifically the decider should decide based on this report (if any)
9. **Upcoming milestones / next period focus**
10. **Metrics dashboard** (if applicable) — KPIs vs targets with trend
11. **Appendix** — detail behind summary

**RAG calibration:**
- **Green:** on track; risks managed; no decisions needed
- **Amber:** at risk; some slippage or risk needs attention; decisions may be needed soon
- **Red:** off track; needs immediate intervention; specific decisions needed now

Don't sandbag (overstating risk) or sugarcoat (understating). Rationale must justify the color.

### Step 3c — V-C: Gap analysis structure

**Sections:**

1. **Scope** — what capability/process/system being analyzed; why (trigger for analysis)
2. **Target state** — desired future state (must exist; if missing, flag as critical prerequisite)
3. **Current state** — observable today, with evidence (data, observations, SME input)
4. **Gap table** — main content:

   | # | Gap dimension | Current state | Target state | Gap size | Priority | Root cause (hypothesis) |
   |---|---|---|---|---|---|---|

   - Gap dimension examples: people/skills, process/workflow, technology/system, data, policy/governance, structure/org
   - Gap size: Small / Medium / Large — calibrate with evidence
   - Priority: P0 (must close) / P1 (should close) / P2 (nice to close)

5. **Prioritization rationale** — how priorities assigned (impact × effort × dependency)
6. **Recommended actions** — table:

   | # | Recommended action | Addresses gap(s) | Effort (T-shirt) | Dependencies | Expected outcome | Owner suggestion |
   |---|---|---|---|---|---|---|

7. **Roadmap suggestion** — sequencing of recommendations (which first, why)
8. **Risks of not closing the gaps** — consequences if status quo continues
9. **BA's recommendation** — proposed prioritized plan

### Step 3d — V-D: SWOT analysis structure

**Sections:**

1. **Scope** — what's being analyzed (company / product / project / capability)
2. **Time horizon** — SWOT is point-in-time; specify timeframe
3. **The 2×2 matrix:**

   | Internal | External |
   |---|---|
   | **Strengths** (positive, internal) | **Opportunities** (positive, external) |
   | **Weaknesses** (negative, internal) | **Threats** (negative, external) |

4. **Each quadrant: list 3-7 items, specific and evidence-backed**
   - Bad: "Good team" / "Bad processes"
   - Good: "Engineering team has 5 senior backend engineers with payments domain expertise (3+ years tenure)" / "Manual reconciliation process takes 2 person-days per month-end, causes Finance bottleneck"

5. **Strategic implications via TOWS combinations:**

   - **SO (Strength-Opportunity) strategies:** how to use strengths to capture opportunities
   - **WO (Weakness-Opportunity) strategies:** what weaknesses to fix to enable opportunities
   - **ST (Strength-Threat) strategies:** how to use strengths to defend against threats
   - **WT (Weakness-Threat) strategies:** what to avoid / mitigate (defensive moves)

6. **Top 3-5 strategic recommendations** — derived from the TOWS combinations, prioritized
7. **BA's recommendation** — proposed top 1-2 strategic moves

**SWOT done well goes beyond the quadrants** — the TOWS combinations are where the analytical value sits. A SWOT without strategic implications is just a list.

### Step 4 — Self-check (rigor)

**Universal:**
- Facts distinguished from opinions (label opinions as "BA observation" or "recommendation")
- Numbers traceable to source
- Critical prerequisites surfaced at top
- BA's recommendation labeled as such (where applicable), distinct from facts
- Assumptions captured

**V-A specific:**
- ≥2 alternatives considered (including "do nothing")
- Impact analyzed across all 4 dimensions (scope/schedule/cost/quality)
- Risks have likelihood + impact + mitigation
- Approval authority identified per change-control policy
- "Cost of rejection" included (not just cost of approving)

**V-B specific:**
- RAG rationale provided (not just a color)
- Milestones show planned vs actual/forecast
- Blockers section explicit (or "None" stated)
- Decisions-needed section explicit
- Trend information where data permits

**V-C specific:**
- Target state defined before current state (otherwise gap is undefined)
- Current state has evidence (data, observations, source)
- Gaps prioritized with rationale (not just listed)
- Each recommendation maps to ≥1 gap (and each high-priority gap has ≥1 recommendation)
- Cost of inaction stated

**V-D specific:**
- Each item is specific and evidence-backed (not generic)
- Strengths/Weaknesses internal; Opportunities/Threats external (don't confuse)
- TOWS combinations produced (not just the four quadrants)
- Top recommendations derived from analysis, not pre-decided

### Step 5 — Surface what's likely missing

**Common omissions per variant:**

V-A change request:
- Cost of NOT changing (status quo has costs too)
- Cumulative impact (this change + previous changes already approved)
- Second-order effects on other projects / teams

V-B status report:
- Trend (just snapshot, no comparison to last period)
- Forward-looking risks (only backward-looking accomplishments)
- Decisions-needed section (assumes leadership will infer — they won't)

V-C gap analysis:
- Cost of inaction
- Dependencies between recommendations (some can't start until others done)
- Capacity check (does the team have bandwidth for the recommendations?)

V-D SWOT:
- Time horizon (SWOT changes over time; specify when)
- External validity (opportunities and threats from genuine external scan, not assumed)
- Connection to action — SWOT without TOWS is half-done

### Step 6 — Output

Default: Markdown in chat. For executive audience, often shorter / executive summary up front.

For file: read `/mnt/skills/public/docx/SKILL.md` first. Status reports often want .docx for distribution; change requests often need formal format per organization template.

**Canonical output order** (adjust per variant):
1. Title + audience note
2. Metadata
3. Critical prerequisites (if any)
4. Executive summary (1 paragraph — required for V-B, V-D; optional for V-A, V-C if long)
5. Variant-specific main content
6. BA's recommendation (where applicable)
7. Open questions / decisions needed
8. Assumptions appendix

### Step 7 — Assumptions appendix

Same pattern. Group:
1. **Critical prerequisites** (flagged)
2. **Scope** — variant choice, what's in/out
3. **Data / evidence** — sources of facts; mark estimates vs measured
4. **Recommendation rationale** — why this recommendation over alternatives
5. **Behavioral defaults** — when multiple framings were sensible, which chosen

---

## Compose with other BA skills

- **Gap analysis recommendations → change requests** (one per major recommendation)
- **Change request impact analysis → updated process model** (`ba-process-model`)
- **Decisions in status report → captured in meeting minutes** (`ba-stakeholder-meeting` V-C)
- **SWOT strategic moves → feature specs for new initiatives** (`ba-feature-spec`)

---

## Language handling

Vietnamese canonical:

| English | Vietnamese |
|---|---|
| Change request | Yêu cầu thay đổi |
| Problem statement | Mô tả vấn đề |
| Proposed change | Đề xuất thay đổi |
| Impact analysis | Phân tích tác động |
| Approval criteria | Tiêu chí phê duyệt |
| Status report | Báo cáo tiến độ |
| RAG status | Trạng thái RAG (Đỏ/Vàng/Xanh) |
| Blockers | Vướng mắc |
| Gap analysis | Phân tích khoảng cách |
| Current state / Target state | Trạng thái hiện tại / Trạng thái mục tiêu |
| Recommended actions | Hành động đề xuất |
| Strengths / Weaknesses / Opportunities / Threats | Điểm mạnh / Điểm yếu / Cơ hội / Thách thức |
| Strategic implications | Hàm ý chiến lược |
| BA's recommendation | Đề xuất từ BA |

---

## Common anti-patterns

- **Advocacy disguised as analysis** — only presenting one side; alternatives forgotten or strawmanned
- **Recommendation without rationale** — "We should X" without "because Y"
- **RAG color without rationale** — leaves reader guessing why
- **Status report without decisions-needed** — reader doesn't know what to do
- **Gap analysis without prioritization** — flat list of every gap; no guidance which to address first
- **SWOT without TOWS** — just four quadrants, no strategic implications
- **Generic SWOT items** ("strong team", "weak processes") — be specific, evidence-backed
- **Confusing internal vs external in SWOT** — Strengths/Weaknesses internal to entity; Opportunities/Threats external
- **Speculation as fact** — opinions need labels ("BA observation", "estimate")
- **Sandbagging or sugarcoating** in status reports — undermines trust over time

## See also

- `references/variants.md` — per-variant details for all 4 variants
- `references/examples.md` — annotated examples: full change request, full status report, gap analysis excerpt, complete SWOT with TOWS
