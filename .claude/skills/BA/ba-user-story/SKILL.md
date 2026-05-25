---
name: ba-user-story
description: Generate Agile user stories and acceptance criteria for business analysts. Use when user wants to write a user story, create AC, split an epic, or break down a feature for dev team. Triggers on "viết user story", "tách story", "acceptance criteria", "AC cho feature", "chia nhỏ epic", "mô tả feature cho dev", "write user story", "split this epic", "AC for this feature". Produces INVEST-compliant stories with Given-When-Then AC, story-scoped (2-5 AC per story typically). Stays within BA scope (intent, rules, flows, AC); does not cross into developer territory (schema, endpoints, code, tech choice). For detailed AC with BR traceability at feature level (10+ AC linked to business rules, formal FRD), use ba-feature-spec. For formal Cockburn use case with main scenario + extensions (enterprise/regulated), use ba-use-case.
---

# BA User Story & Acceptance Criteria

Help a Business Analyst produce user stories and acceptance criteria that are ready for a dev team to pick up — and stop cleanly at the BA/dev boundary.

## The BA boundary line — the most important idea in this skill

Most AI-generated user stories fail in one of two ways: they under-deliver (one-liner story, no edge cases) or they over-deliver (write database schemas, design APIs, choose libraries — work that belongs to dev). Both are unhelpful to a BA.

A BA deliverable specifies **WHAT** and **WHY** and the **business HOW** (rules, flows, AC). It hands off to dev **before** the **technical HOW** (schemas, endpoints, code structure, infra, libraries).

Your job is to make WHAT/WHY/business-HOW crystal clear so dev doesn't have to guess business intent — and to **stop** before deciding things dev should decide. If you find yourself writing "the API endpoint should be /v1/users/transfer with method POST", that's a signal you've crossed the line.

**Cover (yes):**
- Business intent and value
- Personas / actors and their permissions at business level
- Business rules (limits, eligibility, when an action is allowed or denied)
- Business flows (happy path, alternative paths, exceptions from business POV)
- Acceptance criteria (Given-When-Then)
- Edge cases the business cares about
- Non-functional concerns at business level (SLA expectation, audit / compliance, what data is sensitive)
- Dependencies and explicit out-of-scope items

**Do not cover (handoff to dev):**
- API endpoint design (URL, method, schema)
- Database design, table structure
- Specific error codes or wire-format error responses
- Technology choice (library, framework, protocol)
- Performance tuning, infra, caching strategy
- Code structure, algorithm choice

When the input is phrased technically ("design the API for X"), reframe internally: produce the BA-level spec (what business operations the API enables, business rules, flows, AC). At the end, add a short section **"Decisions handed to dev"** listing the technical choices dev will need to make, so nothing gets dropped on the floor at the handoff.

---

## Workflow

For any request in scope, follow these steps in order. Don't skip steps because the request looks simple — even small features hide edge cases worth surfacing.

### Step 1 — Read context, decide if you need more

Before generating anything, check what you have. Minimum context:

1. What is the feature / capability? (one-sentence summary)
2. Who uses it? (at least one persona, with role / permission level)
3. What is the business value? (why it exists)
4. Any non-obvious business rule? (limits, eligibility, compliance)

If two or more of these are missing or vague, ask the user — but ask the **fewest** questions needed, batched into one message, max three. Don't pepper. If only one item is fuzzy, default to the most common case for the domain and proceed — don't interrupt the output with inline disclaimers.

Calibration: one-line ambiguous request ("write user stories for dashboard") — ask. Paragraph with most context — proceed.

**Track every assumption** you make from here on — scoping decisions in this step, and the inline assumptions you'll make in later steps (specific numbers, default thresholds, default policy choices). They all get consolidated in the **assumptions appendix at the end** (Step 8) for the BA to confirm before sign-off. This is how the skill stays transparent without slowing the output down.

### Step 2 — Decide: one story or an epic?

If the request describes work taking more than roughly three days of dev effort, or involves more than one persona doing different things, or has more than five-to-six AC — it's an epic. Split it.

Splitting patterns (full examples in `references/slicing-patterns.md`):
- By workflow step (browse → select → pay → confirm)
- By persona / role (sender vs receiver, customer vs admin)
- By business rule variation (eligibility tier A vs B)
- By data variation (one-time vs recurring, CSV vs Excel)
- Happy path first, then exception paths as later stories

Choose the pattern that maps to how the team would actually deliver and demo value incrementally. Default: workflow step.

### Step 3 — Write each story

Canonical format:

> **Story [ID]: [Short title]**
>
> **As a** [specific persona with role and relevant state]
> **I want** [observable goal, not implementation]
> **So that** [business value, measurable if possible]

Be specific.

- "As a user" — weak. "As a registered retail customer with verified phone number" — strong.
- "I want to use the system" — weak. "I want to send money to a phone number that doesn't yet have an account" — strong.
- "So I can use the feature" — tautological. "So I can pay individuals without needing their account number" — concrete value.

Check **INVEST** before moving on:
- **I**ndependent — can be built without waiting on a sibling story
- **N**egotiable — leaves room for dev to decide HOW
- **V**aluable — produces value visible to a stakeholder
- **E**stimable — dev can estimate effort (means spec is clear enough)
- **S**mall — fits ~3 days; if larger, split again
- **T**estable — AC are objectively verifiable

If a story fails any INVEST letter, fix the story before writing its AC.

### Step 4 — Write acceptance criteria

Use Given-When-Then. Target counts per story:
- 1-2 AC for happy path
- 2-4 AC for edge / alternative paths
- 1-3 AC for negative / error cases
- At least 1 AC per business rule (limit, eligibility, compliance check)

Run the story against this **edge case taxonomy** — ask each question, write AC only for ones that apply:

- **Boundary** — min, max, zero, empty, very large input
- **State** — entity is in a state that disallows the action (locked, expired, pending verification)
- **Permission** — persona lacks the right role / tier
- **Concurrency** — two actions at once (double-submit, race from business POV — not technical)
- **Time** — outside business hours, after cutoff, during maintenance window, timezone
- **Data integrity** — stale data, conflict with existing record
- **Compliance** — audit log requirement, regulatory limit, PII handling, consent

You don't need AC for every category. You do need to **ask** about each.

Format:

> **AC[N] — [short scenario name]**
> - **Given** [precondition / starting state]
> - **When** [action / event]
> - **Then** [observable outcome]
> - **And** [additional outcome if needed]

The "Then" must be observable from a user's or business's perspective — no technical leak.
- Wrong: "Then the API returns HTTP 200."
- Right: "Then the recipient sees the transferred amount in their balance and receives a confirmation notification."

### Step 5 — Self-check before delivering

Run through this list. If anything fails, fix it before showing the user:

- Every story passes INVEST
- Every story has a specific persona (not just "user")
- Business value is concrete, ideally measurable
- AC are observable from user / business POV, no technical leak
- Edge cases cover at least: one boundary, one error / negative, one rule-driven case (if rules exist)
- No technical decisions snuck in (no endpoints, no schema, no library names)
- For epics: stories can be independently demo-ed in priority order
- Domain terms and acronyms from the prompt are preserved, not "translated" into generic terms
- **Every assumption made during Steps 1–4 is captured for the appendix** (Step 8) — scan AC for inline numbers / thresholds / defaults you introduced and make sure they're on the list

### Step 6 — Proactively surface what the user may have missed

This is the highest-value step. After writing stories, scan for likely-missing pieces and list them at the end. Don't auto-write them — list as suggestions so the user decides.

Things commonly forgotten:
- **Forgotten personas** — admin / CS handling disputes, the system itself (scheduled job, webhook callback), partner-side actors
- **Forgotten flows** — forgot password, cancellation, refund, dispute, rollback, retry, undo
- **Forgotten compliance** — audit log, PII masking, consent capture, regulatory reporting
- **Forgotten visibility** — notifications (in-app, push, SMS, email), in-app status indicators, receipts / proof artifacts
- **Forgotten edge data** — decimals, multi-currency, timezone, locale, character encoding
- **Forgotten downstream** — who else cares when this event happens — accounting, BI, partner systems, reporting

Format as a separate section: **"Stories you may want to add"**, with one-line descriptions and a question mark — leave the call to the user.

### Step 7 — Output

Default: Markdown in chat. The user typically pastes into Jira / Confluence / Notion.

If the user asks for a file ("xuất ra Word", "save as docx", "tạo file"), read `/mnt/skills/public/docx/SKILL.md` first, then produce a `.docx` with hierarchy: Epic title → Stories as H2 → AC as H3.

For many stories from an epic split, also produce a markdown summary table at the top with columns: ID, Title, Persona, Priority (mark as TBD if user didn't say), Status (default: To Refine).

**Canonical output order** (apply for both chat and docx):

1. Epic title and short summary (1-2 sentences)
2. Summary table of stories (only if epic split, ≥2 stories)
3. Each story with its AC, in priority order
4. "Stories you may want to add" — from Step 6
5. "Decisions handed to dev" — only if the prompt had technical framing or the spec implies non-trivial dev choices
6. "Phụ lục: giả định cần xác nhận" — assumptions appendix (Step 8)

The appendix goes **last**, after everything else. This positions it as a sign-off gate — the BA reads the spec, then commits to the assumptions before forwarding.

### Step 8 — Assumptions appendix (consolidate at the end for confirmation)

Throughout Steps 1–6, every time you make an assumption — scoping decisions, specific numeric thresholds, default policy choices, behavioral defaults — keep a running mental list. At the end of the output, append a section titled **"Phụ lục: giả định cần xác nhận"** (Vietnamese) or **"Assumptions to confirm"** (English).

**Why this is required:** a BA reviewing 5+ stories can easily miss inline assumptions buried in AC (e.g., "pending expires after 7 days", "max 5 pending transactions per user", "AML alert threshold = 50M VND"). Consolidating at the end forces explicit sign-off and prevents "I didn't realize you assumed that" surprises when the spec hits stakeholders or dev.

**What counts as an assumption to log:**
- Scoping decisions — which personas, channels, currencies, geographies are covered (and by implication, what's out of scope)
- Specific numeric thresholds you defaulted — limits, retry counts, expiry windows, batch sizes, ngưỡng AML
- Default policy choices — refund window, masking rules, locale defaults, business-hour cutoffs
- Behavioral defaults where multiple sensible options exist — e.g., "block self-transfer" vs "allow", "auto-reverse on expiry" vs "manual review"

**What does NOT need logging:**
- Standard industry patterns the user implicitly accepted (Given-When-Then, INVEST)
- Things the user explicitly stated in the prompt
- Self-evident consequences (if user says "P2P transfer", you don't log "assuming peer-to-peer")

**Format** — table if ≥3 assumptions (the common case), bullets if 1–2:

```
## Phụ lục: giả định cần xác nhận

Mình đã tự đặt các giả định sau để đi tiếp. Vui lòng review — nói rõ giả định nào bạn muốn đổi, mình sẽ cập nhật stories và AC tương ứng.

| # | Giả định | Ảnh hưởng đến | Lý do mặc định |
|---|----------|---------------|----------------|
| 1 | [Giả định một dòng] | [Story/AC nào, hoặc "Toàn epic"] | [Một câu giải thích default này hợp lý] |
| 2 | ... | ... | ... |
```

If many assumptions (>8) or they fall into clear groups, organize by group: **Scope assumptions**, **Policy thresholds**, **Behavioral defaults**.

The "Lý do mặc định" column matters — it shows the BA why this default is reasonable, so they can quickly decide "OK, accept" vs "no, change to X". Without it, they have to think from scratch.

End the appendix with a one-line conversational prompt — "Bạn nói rõ cần đổi gì → mình cập nhật" or similar. Avoid bureaucratic phrasing like "Please review and provide approval".

**Skip the appendix only** if there are zero assumptions (rare — usually means the prompt was fully specified or it was a trivial single-story output).

---

## Language handling

Detect the user's language from the prompt and match. If the user writes Vietnamese, write stories in Vietnamese. Canonical keywords can stay English ("As a / I want / So that", "Given / When / Then") since they're industry-standard — or use Vietnamese equivalents ("Là một / Tôi muốn / Để mà", "Cho rằng / Khi / Thì") — match what the user uses. Don't switch languages mid-output.

Preserve domain terminology exactly as the user uses it. "ví điện tử" stays "ví điện tử", not "e-wallet". "đối tác" stays "đối tác", not "partner". Don't auto-translate.

---

## Common anti-patterns to avoid

- **Generic persona** ("As a user") — push for specificity (role + state)
- **Tautological value** ("So I can use the feature") — push for measurable benefit
- **Implementation leaking into "I want"** ("I want the system to call the validation API") — should be "I want to know if my input is valid"
- **Mega-story** (20 AC in one story) — split
- **AC written as test steps** ("Click button X, see screen Y") — that's a test case, not AC. AC describes observable outcomes regardless of UI navigation
- **Missing negative AC** — every story with a business rule needs at least one AC for "rule violated"
- **Over-eager dev decisions** — never write "use OAuth2", "endpoint POST /api/...", "store in users table". Those are dev's call. Surface them in the "Decisions handed to dev" section instead

---

## Domain hints (apply only if user mentions domain)

These are reminders, not requirements. Use to remember what to ASK about, not what to bake in.

**Fintech / banking / payment:** transaction limits per tier, idempotency at business level (no duplicate transactions if user double-taps), reconciliation cycles, audit log, KYC / verification status check, sanction / PEP screening for cross-border, fraud risk step-up, refund / reversal flow.

**E-commerce / retail:** inventory consistency, order state transitions (placed → paid → packed → shipped → delivered → returned), payment-shipping sync, return / refund flow, multi-currency, tax / VAT computation, promo / voucher stacking rules.

**Enterprise software (ERP / CRM):** role-based permissions matrix, approval workflows (single / multi-step), audit trail, integration with upstream / downstream modules, data ownership and visibility scope.

**B2B / partner integration:** partner onboarding, partner-level permissions and quotas, partner SLA expectations, webhook / callback flow, reconciliation between partner records and own records, partner support / dispute flow, sandbox vs production parity.

Only surface relevant ones. Don't shoehorn.

---

## See also

- `references/slicing-patterns.md` — detailed examples of each story-splitting pattern, with selection guidance
- `references/examples.md` — annotated good vs bad user stories showing the principles in action
