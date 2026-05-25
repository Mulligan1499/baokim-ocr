---
name: ba-stakeholder-meeting
description: Produce stakeholder analysis, RACI matrices, meeting minutes with decision logs, and communication plans. Use whenever the user wants to analyze stakeholders, build a RACI matrix, document meeting decisions, plan stakeholder communication, or capture stakeholder dynamics. Triggers on phrases like "phân tích stakeholder", "stakeholder analysis", "RACI", "biên bản họp", "meeting minutes", "decision log", "communication plan", "kế hoạch giao tiếp", "power-interest matrix", "ma trận quyền lực". Produces stakeholder maps (power / interest matrix), RACI tables (R/A/C/I), meeting minutes (attendees, agenda, decisions, action items, parking lot), or communication plans (stakeholder × channel × frequency). Stays at business observation level — captures what stakeholders care about, who decides what, what was agreed. Does NOT cross into psychological assessments, HR decisions, or personal judgments about individuals.
---

# BA Stakeholder & Meeting

Produce stakeholder-related deliverables that BAs use for people/process management: stakeholder analysis, RACI matrices, meeting minutes with decision logs, and communication plans.

## The BA boundary for stakeholder/meeting work

**Cover:**
- Stakeholders' **business interests** (what outcomes they care about, what they fear, what they need from this initiative)
- Stakeholders' **power and interest level** (high/low, observable from role + behavior)
- **Decision authority** (who decides what — business roles, not personalities)
- **Communication needs** (channel, frequency, content depth)
- **Meeting outputs** — decisions made, action items, parking lot, attendees, agenda
- **Influence patterns** at business level (e.g., "Finance head's approval typically gates budget")

**Do NOT cover:**
- Psychological assessments ("X is anxious", "Y is aggressive") — surface behavior, not personality
- HR-level judgments (performance, capability assessments)
- Personal opinions about individuals
- Workplace politics interpretations beyond what's neutrally observable
- Confidential information (compensation, performance reviews)

**Acid test:** would this stakeholder be comfortable reading what you wrote about them? If no, you've crossed into personal/judgmental territory. Stay neutral and business-focused.

---

## Step 0 — Detect variant

| Variant | Direct trigger signals | Context signals | Output |
|---|---|---|---|
| **V-A — Stakeholder analysis** | "phân tích stakeholder", "stakeholder map", "power-interest", "ai liên quan tới project" | Project kickoff context, identifying who's involved, mapping influence | Stakeholder list + power/interest matrix + engagement strategy |
| **V-B — RACI matrix** | "RACI", "trách nhiệm cho project", "ai làm gì", "responsibility matrix", "phân công" | Defining roles/responsibilities, conflict resolution about ownership | RACI table (activities × roles) + decision rules |
| **V-C — Meeting minutes** | "biên bản họp", "meeting minutes", "ghi nhận họp", "decision log", "minutes cho cuộc họp" | Just attended a meeting, captured decisions/actions, post-meeting documentation | Structured minutes with decisions, actions, parking lot |
| **V-D — Communication plan** | "kế hoạch giao tiếp", "communication plan", "cadence họp", "communication strategy" | Setting up project comm rhythm, defining update channels per stakeholder | Stakeholder × channel × frequency × content matrix |

**Detection approach:**

1. First, scan for **direct trigger signals** (exact phrase match) — highest confidence
2. Then check **context signals** (situation described by user) — refine confidence
3. If user describes a situation but doesn't name the deliverable explicitly, infer from context:
   - "Vừa họp xong với team" → likely V-C minutes
   - "Project mới khởi động, cần xác định ai liên quan" → likely V-A stakeholder analysis
   - "Confusion về ai duyệt cái gì" → likely V-B RACI
   - "Sponsor phàn nàn không biết tiến độ" → likely V-D comm plan
4. If signals point to multiple variants, ask ONE clarifying question with concrete options:

```
Yêu cầu có thể fit nhiều variant. Bạn muốn:

(a) Phân tích các bên liên quan + chiến lược tương tác (V-A stakeholder analysis)
(b) Bảng phân công ai làm gì, ai duyệt gì (V-B RACI matrix)
(c) Biên bản cuộc họp [tên/ngày họp] (V-C meeting minutes)
(d) Kế hoạch giao tiếp định kỳ với stakeholders (V-D communication plan)
```

5. If user doesn't answer, **default to V-A** (most common starting point for people-process work) and note assumption.

**Don't ask multiple clarifying questions.** One question, concrete options, default if no answer.

Variants may compose: V-A often precedes V-B and V-D for the same initiative. V-C is standalone but often references stakeholders defined elsewhere.

Record variant + rationale in metadata top.

---

## Workflow

### Step 1 — Read context

Universal minimum:
1. What's the initiative / project / meeting? (scope)
2. Variant-specific (see `references/variants.md` for full questions)
3. Who is the user (BA) and what's their position relative to the stakeholders? (Affects what's appropriate to write)

Ask the fewest needed (max 3). Default + track for appendix.

**Critical prerequisites.** For V-B and V-D: if key roles aren't yet defined (e.g., "we don't have a product owner yet"), flag — RACI and comm plan are meaningless without role clarity.

**Sensitivity note.** This skill produces content describing real people / teams. Always:
- Use roles, not personal names where possible
- Avoid speculative judgments
- Frame "concerns" as business-observable concerns, not psychological reads
- If user wants to capture politically sensitive context, suggest a separate private note rather than the main deliverable

### Step 2 — Determine sections

Universal:
- Metadata (variant, variant rationale, scope, status, date)
- Critical prerequisites (if any)
- Main content (variant-specific)
- Open questions
- Assumptions appendix

Variant-specific main content described per variant below.

### Step 3a — V-A: Stakeholder analysis

**Step 3a.1 — Identify stakeholders**

Brainstorm using categories:
- **Sponsor / funder** — who pays for / authorizes this
- **End users** — who uses the output of the initiative
- **Implementers** — who builds (dev, design, content, ops)
- **Reviewers / approvers** — who must sign off (Compliance, Security, Legal, Architecture)
- **Beneficiaries** — who benefits from outcome (Sales, Marketing, Customer Support, Finance)
- **Disrupted parties** — who has their work changed by the initiative
- **External parties** — partners, vendors, regulators, customers

For each: name as **role** (not personal name unless user explicitly asks). If personal names needed, get explicit user input — never invent names.

**Step 3a.2 — Assess power and interest**

For each stakeholder, assess:
- **Power** (ability to affect the initiative): High / Medium / Low
- **Interest** (degree of attention they pay): High / Medium / Low

Calibration:
- **Power High** = can veto, redirect, kill, or accelerate the initiative
- **Power Medium** = can advocate, recommend, or contribute significantly
- **Power Low** = affected but cannot influence outcome
- **Interest High** = actively follows, asks for updates, has opinions
- **Interest Medium** = aware, attends key meetings, follows summary updates
- **Interest Low** = needs to know occasionally, doesn't engage actively

**Step 3a.3 — Build the power/interest matrix**

Output as a 2×2 matrix (Mermaid quadrant or markdown table):

| | Interest LOW | Interest HIGH |
|---|---|---|
| **Power HIGH** | Keep satisfied (manage proactively, brief regularly) | Manage closely (engage deeply, co-create, key influence) |
| **Power LOW** | Monitor (light updates) | Keep informed (share progress, gather feedback) |

For each stakeholder, place them in a quadrant + state the engagement strategy.

**Step 3a.4 — Engagement strategy per stakeholder**

For each stakeholder, document:
- Quadrant
- Business interest (what they care about, in 1-2 sentences — observable from role + initiative context)
- Engagement strategy (1-3 sentences — what frequency, what depth, what channel)
- Owner (who on the team has the relationship)

### Step 3b — V-B: RACI matrix

**Step 3b.1 — Identify activities / decisions**

List the major activities and decisions in the initiative. Granularity: 8-20 items typically. Too granular → unreadable; too coarse → not useful.

Examples: "Define scope", "Approve architecture", "Sign off on UAT", "Approve go-live", "Manage incident response", "Communicate to customers", etc.

**Step 3b.2 — Identify roles**

List the roles involved. Use role names (Product Owner, BA, Tech Lead, QA Lead, Sponsor, Compliance Reviewer), not personal names.

**Step 3b.3 — Assign R/A/C/I per activity × role**

- **R — Responsible** (does the work)
- **A — Accountable** (one and only one; answers for the outcome)
- **C — Consulted** (asked for input before decision)
- **I — Informed** (told after decision)

**Critical rules:**
- Each activity has **exactly one A** (one accountable)
- Each activity has **≥1 R** (at least one responsible — can be same person as A)
- C and I are optional but distinguish carefully: C = consulted before, I = informed after
- Avoid "R/A" together unless deliberate (same person both does and is accountable — common in small teams)

**Step 3b.4 — Decision rules**

After the table, document:
- Escalation rules ("if R and A disagree, escalate to Sponsor")
- Override rules ("Sponsor can override any decision in scope")
- Tie-breakers ("If Compliance and Product disagree, Compliance wins per [policy]")

### Step 3c — V-C: Meeting minutes

**Standard meeting minutes structure:**

1. **Metadata** — date, time, duration, location/medium (Zoom/in-person), recorder
2. **Attendees** — list with role; mark absent invitees
3. **Agenda** — what was planned
4. **Decisions** — each decision: what, owner of decision, date effective, rationale, dissent (if any)
5. **Action items** — each action: what, owner, deadline, success criterion
6. **Discussion notes** — key points discussed (not verbatim — synthesized)
7. **Parking lot** — items raised but deferred; revisit policy
8. **Next meeting** — when, who, agenda preview

**Decision log format (separate or embedded):**

| ID | Decision | Owner | Date | Rationale | Dissent | Effective |
|---|---|---|---|---|---|---|

**Action item format:**

| ID | Action | Owner | Deadline | Status |
|---|---|---|---|---|

**Rules:**
- Every decision has an owner
- Every action has an owner + deadline (not "ASAP")
- Discussion notes are factual, not interpretive ("X raised concern about Y" not "X seemed worried about Y")
- Parking lot items have a revisit trigger or owner who'll bring them back

### Step 3d — V-D: Communication plan

**Communication plan structure:**

A matrix: **Stakeholder × Channel × Frequency × Content × Owner**.

| Stakeholder | Channel | Frequency | Content focus | Owner |
|---|---|---|---|---|
| Sponsor | 1:1 + monthly written status | Monthly written, ad-hoc 1:1 | Strategic progress, risks, decisions needed | BA + PM |
| Dev team | Daily standup + Slack | Daily standup, async Slack | Day-to-day execution, blockers | Tech Lead |
| Compliance | Email summary + checkpoint meeting | Weekly email, quarterly meeting | Compliance touch points, risks, attestations | BA |
| End users | Monthly newsletter + release notes | Monthly + per release | What's changing, why, how to adapt | Product + Marketing |

**Calibration guidance:**
- Don't over-communicate to "low interest" stakeholders (you'll lose their attention)
- Don't under-communicate to "high power" stakeholders (surprise = damaged trust)
- Match channel to content sensitivity (1:1 for risks, email for status, demo for milestones)

### Step 4 — Self-check (rigor)

**Universal rigor:**
- Roles used, not personal names (unless user explicitly provided names)
- No psychological / HR-style judgments
- No speculative interpretations beyond observable
- Critical prerequisites surfaced
- Every assumption captured for appendix

**V-A specific:**
- Every stakeholder placed in a quadrant
- Every quadrant has an engagement strategy
- Business interests stated for each (not just role descriptions)
- No "personality" descriptions

**V-B specific:**
- Every activity has exactly one A
- Every activity has ≥1 R
- C and I distinguished (not just "involved")
- Activities are specific enough to be actionable
- Escalation and tie-breaker rules stated

**V-C specific:**
- Every decision has owner + date + rationale
- Every action item has owner + deadline (no "ASAP" or "soon")
- Discussion notes are factual, not interpretive
- Parking lot has revisit policy
- Decisions distinguished from discussions

**V-D specific:**
- Every stakeholder identified in V-A (or parallel analysis) has a comm plan row
- Frequency calibrated to power/interest level
- Content focus distinguished per stakeholder (not generic)
- Owner identified for each comm channel

### Step 5 — Surface what's likely missing

Common omissions:
- **Hidden stakeholders** — Legal, DPO/Privacy, Internal Audit, Information Security — often forgotten until late
- **External stakeholders** — Regulators, key customers, partners
- **Indirect beneficiaries / disrupted parties** — teams whose work changes downstream
- **End users themselves** — sometimes treated as abstract "users" without representation

For V-B specifically:
- Decision rights vs execution rights — sometimes blurred (the person who does the work may not have decision authority)
- Activities related to risk handling, escalation, change control

For V-C:
- Decision rationale missing — "we decided X" without the why is useless 6 months later
- Implicit decisions — things assumed but never formally agreed

For V-D:
- Crisis / incident communication path (separate from steady-state)
- Communication to skip-level executives (sponsor's boss)

### Step 6 — Output

Default: Markdown in chat.

For file: read `/mnt/skills/public/docx/SKILL.md` first; produce .docx. Tables transfer well.

For meeting minutes specifically: often a quick deliverable — markdown in chat is usually preferred for fast distribution; offer email-friendly version on request.

**Canonical output order:**
1. Document title + scope (1 line)
2. Metadata (variant, variant rationale, status, date)
3. Critical prerequisites (if any)
4. Variant-specific main content
5. Open questions
6. Assumptions appendix

### Step 7 — Assumptions appendix

Same pattern. Group:
1. **Critical prerequisites** (flagged)
2. **Scope** — variant choice, who's in/out of analysis
3. **Stakeholder assessment** — power/interest assignments are BA's judgment based on signals; mark each assignment with rationale; user can override
4. **Role definitions** (V-B) — what each role means in this context (e.g., "what 'Product Owner' specifically covers here")
5. **Engagement defaults** (V-A, V-D) — chosen cadence and channel where user didn't specify

---

## Compose with other BA skills

- **Decisions made in meeting → change requests** → `ba-change-reporting` (V-A change request)
- **Action items requiring formal spec** → `ba-feature-spec`
- **RACI activities flowing into a process** → `ba-process-model`

---

## Language handling

Vietnamese canonical:

| English | Vietnamese |
|---|---|
| Stakeholder | Bên liên quan |
| Power / Interest | Quyền lực / Quan tâm |
| Engagement strategy | Chiến lược tương tác |
| RACI | RACI (loanword) |
| Responsible / Accountable / Consulted / Informed | Thực hiện / Chịu trách nhiệm / Tham vấn / Thông báo |
| Meeting minutes | Biên bản họp |
| Action item | Việc cần làm |
| Decision log | Nhật ký quyết định |
| Parking lot | Danh sách gác lại |
| Communication plan | Kế hoạch giao tiếp |

---

## Common anti-patterns

- **Personal names without permission** — use roles; if user gives names, use them, otherwise role-only
- **Psychological / personality descriptions** — surface behavior, not personality
- **Multiple A in RACI** — defeats accountability; pick one
- **No A in RACI** — every activity needs accountability
- **Action items without owner or deadline** — "TBD" with rationale is OK, "ASAP" is not
- **Decisions without rationale** — won't be defensible later
- **Comm plan with same frequency for all stakeholders** — defeats the purpose

## See also

- `references/variants.md` — per-variant details for all 4 variants
- `references/examples.md` — annotated examples per variant
