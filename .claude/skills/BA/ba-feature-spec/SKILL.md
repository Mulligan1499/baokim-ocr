---
name: ba-feature-spec
description: Produce BA-grade specification documents — FRD internal feature spec, API contract spec (produce or consume), multi-system integration spec, or BRD (business-level). Use when user wants spec, FRD, BRD, feature document, API contract, or integration spec. Triggers on "viết spec", "FRD", "BRD", "spec cho đối tác", "API contract", "tạo API cho partner", "tích hợp 2 hệ thống", "data sync", "write a spec", "API spec". Produces structured doc with critical prerequisites, BR↔AC traceability matrix (5-20+ AC linked to business rules), flows, NFR, decisions handed to dev, glossary, assumptions appendix. Stays at BA scope (WHAT, WHY, business HOW); does not cross into technical HOW. 4 variants (V-A FRD internal, V-B API contract, V-C multi-system integration, V-D BRD). For story-scoped AC in agile (2-5 per story), use ba-user-story. For formal Cockburn use case (enterprise/regulated), use ba-use-case.
---

# BA Feature / Spec Document

Produce specification documents at BA quality — structured enough that dev, QA, and stakeholders all get what they need; focused enough that the doc doesn't sprawl.

## The BA boundary line

A BA spec describes WHAT, WHY, and the business HOW (rules, flows, AC). It hands off to dev **before** the technical HOW (schemas, endpoints, code structure, infra, library choice). If you find yourself writing things like "use REST with POST /api/v1/...", "create a table users(id, phone, status)", or "implement OAuth2 client_credentials" — stop. That's dev's choice.

**Cover (yes):**
- Business intent, scope, success criteria
- Personas / actors and their business-level permissions
- Business rules (limits, eligibility, calculations, validations)
- Business flows (happy + alternative + exception paths, as text or Mermaid)
- Acceptance criteria per functional area
- Non-functional concerns at business level (SLA expectations, audit, compliance, data sensitivity)
- Data considerations at business level (what data, sensitivity, retention need — never schema)
- Dependencies and prerequisites

**Do not cover (handoff to dev):**
- Endpoint URLs, HTTP methods, request/response schemas
- Database schema, table design, indexing strategy
- Specific error codes / wire format
- Technology choices (REST/GraphQL/gRPC, library, framework)
- Performance tuning, infra, caching
- Code structure, algorithms

When the prompt is framed technically ("design the API for partners"), reframe internally: produce a BA-level spec describing what business operations the API enables, partner-level rules, integration flow expectations, AC. At the end, the "Decisions handed to dev" section explicitly lists technical choices dev must make — so nothing falls through the cracks at handoff.

---

## Step 0 — Detect the variant

The spec variant determines which extra sections to add and which questions to ask. Detect early from prompt signals.

**Important: V-D is at a different document level than V-A/B/C.** V-A/B/C are *functional specs* (FRD level — detailed feature/API/integration logic). V-D is *business requirements* (BRD level — business case, ROI, scope boundaries; written BEFORE the functional spec). A project may need BOTH a BRD (V-D) and an FRD (V-A/B/C) — produce them as separate documents.

| Variant | Document level | Signals | Audience |
|---|---|---|---|
| **V-A — FRD: Internal feature** (default) | Functional | "spec cho tính năng X", "FRD cho feature Y", no partner/integration mentioned, single-team scope | Internal dev + QA + product |
| **V-B — FRD: API contract spec** | Functional | API as primary deliverable (whether we *produce* the API or *consume* partner's API). Signals: "API spec", "API contract", "endpoints", "tích hợp với partner X qua API", "B2B API", "webhook spec" | Internal dev + the other party (partner consuming our API, or our team consuming partner's) |
| **V-C — FRD: Multi-system integration** | Functional | 2+ systems integrated as peers (not just API consumption). Signals: "tích hợp 2 hệ thống X và Y", "data sync giữa", "đồng bộ giữa POS và ERP", "ETL", "feed", needs conflict resolution / eventual consistency / bidirectional flow | Internal dev + ops + the other system's owner |
| **V-D — BRD: Business requirements** | Business (pre-functional) | "BRD", "business requirements document", "business case", "ROI", "scope document", "tài liệu nghiệp vụ cho leadership", written BEFORE functional spec | Executives, sponsors, stakeholders, steering committee |

**Boundary clarifications:**
- **V-B vs V-C:** if the deliverable is an API contract (1 party produces, 1 party consumes), use V-B even if both parties are partners. If 2+ systems are peers requiring data sync / conflict resolution, use V-C.
- **V-D vs V-A/B/C:** if user asks for "BRD", use V-D (business level). If user asks for "spec / FRD / functional spec", use V-A/B/C even if there's business context. V-D often precedes V-A/B/C in the same project — they're complementary, not alternatives.

If ambiguous, default to V-A. Always record your variant choice in the appendix (Step 9) so the BA can correct if you picked wrong.

For deep guidance on each variant — extra required sections, variant-specific questions, common pitfalls — read `references/variants.md` and focus on the relevant variant.

---

## Workflow

### Step 1 — Read context, decide if you need more

Universal minimum context:
1. What is this feature / API / integration? (one-sentence summary)
2. Who uses or interacts with it? (primary actor; for partner variant include partner type)
3. What business problem does it solve / value does it create?
4. Any constraints, deadlines, dependencies the BA already knows?

Variant-specific questions live in `references/variants.md`. Read that file for the variant you detected before deciding what to ask.

Ask the **fewest** questions needed, max 3, batched. If only one item is fuzzy, default to the most common case and proceed — don't interrupt with inline disclaimers.

**Large-scale trigger.** If the request implies >50 entities (stores, partners, users, regions, teams) or multi-month / multi-team rollout, *explicitly* ask or assume rollout phasing (pilot → expanded → full). Phasing is a business decision that affects scope, risk, and dependencies. Don't skip it for any spec touching this scale — surface it even if you have to default ("Phasing: pilot 10 cửa hàng → 50 → full 200, mỗi giai đoạn 4-6 tuần").

**Track every assumption** from here on (variant choice, scope decisions, numeric thresholds, default behaviors). They all go into the appendix at Step 9.

**Critical prerequisites.** Some assumptions are *architectural* — if they're false, the entire spec structure is wrong. Examples: "vendor has data-aggregation hub" (Variant C), "partner accepts our authentication standard" (Variant B), "regulator has approved the use case" (any variant). These are NOT routine assumptions. Flag them as **Critical prerequisites** — get them confirmed before sign-off, not buried in the appendix. They surface in two places: a "Critical prerequisites" section at the *top* of the spec (right after Scope), and as a flagged group at the top of the appendix.

### Step 2 — Determine which sections apply

**Universal sections (every variant):**
- Context & objective
- Scope (in / out)
- **Critical prerequisites** (if any architectural assumptions exist — see Step 1)
- Actors / personas
- Business rules
- Acceptance criteria
- Dependencies
- Decisions handed to dev (if any technical implications)
- Assumptions appendix

**Conditional sections — include only if relevant:**
- **Flows** — if any flow is multi-step or has branching; use Mermaid
- **Non-functional requirements** — if any matter at business level (SLA, audit, security sensitivity, compliance flag)
- **Data considerations** — if the feature handles non-trivial data (sensitivity classification, retention, ownership)
- **Glossary** — if ≥3 domain-specific terms in the spec

**Variant-specific extra sections** — see `references/variants.md`:
- Variant B adds: partner onboarding, partner permissions/quotas, webhook expectations, reconciliation, sandbox parity
- Variant C adds: data mapping, sync/async expectation, failure & retry expectations
- Variant D adds: business case, success metrics, stakeholders, key business risks

Don't include a section just to fill the template. If it would only have placeholder text, omit it.

### Step 3 — Write the sections, in priority order

Order of writing (and order in the final document):

1. Context & objective — anchors everything
2. Scope — what's in, what's deliberately not
3. Actors — who matters
4. Business rules — numbered (BR-01, BR-02...)
5. Flows — Mermaid where useful
6. Acceptance criteria — per functional area
7. Variant-specific sections (B/C/D extras)
8. Non-functional, Data — supporting detail
9. Dependencies
10. Open questions / Decisions handed to dev
11. Assumptions appendix (Step 9)

### Step 4 — Section-by-section guidance

**Context & objective** — 3-6 sentences. What this is, why it exists, what changes after it ships. Crisp business framing, not marketing language.

**Scope** — bulleted in-scope and out-of-scope. Be explicit and aggressive about out-of-scope — that's the most useful part for dev (saves arguments later).

**Critical prerequisites** (when applicable) — list any architectural assumption that must be confirmed before sign-off, as a numbered list with each item: what is assumed, why it matters (what depends on it), who can confirm it. Example: "*P-01 — Vendor có data-aggregation hub:* toàn bộ Luồng 1 dựa vào hub này; nếu vendor không có thì middleware phải pull trực tiếp từng cửa hàng, kiến trúc khác hẳn. **Confirm với:** vendor technical contact, trước cutover."

**Actors** — one-line description each. Include system actors (scheduled job, webhook, external system) if relevant. For each: name, role, key business-level responsibilities/permissions.

**Business rules** — number them (BR-01, BR-02...). One rule per item. Short title + statement. For numeric or tabular rules, use a table.

*Cross-reference convention:* when one BR depends on or modifies another, reference it explicitly: "BR-09 applies in scenarios covered by BR-05 source-of-truth conflicts". This makes logic traceable.

*TBD values rule:* if a BR contains "TBD" values (limits, thresholds), every TBD must have an **explicit owner + deadline**: "TBD — Risk team chốt trước sprint planning". TBD without owner is *invalid* — either get an interim default or move the BR to Critical prerequisites.

Example:
- BR-01 — Hạn mức ngày: Tổng số tiền user chuyển trong ngày không vượt hạn mức tier (tier defined in BR-04).
- BR-02 — Self-transfer: Chặn ở platform level; user không thể chuyển cho SĐT đang đăng ký dưới chính account của mình.

**Flows** — for multi-step flows, use a Mermaid sequence diagram or flowchart. Stay at business actor level — never draw infrastructure (no load balancers, queues, microservice X). Keep the platform as a single box "Hệ thống / Wallet / Core system". See `references/examples.md` for Mermaid templates.

*Number-source rule:* any number embedded in a Mermaid diagram (time windows, retry counts, durations, thresholds) MUST be defined in a BR or the appendix — **single source of truth**. Don't duplicate values into diagrams; if you must show a number in the diagram, also reference where it's defined. Drift between diagram and BR/appendix is the most common spec bug.

**Acceptance criteria** — organized per functional area or per business rule, not per user story. This skill produces document-level AC. For INVEST + GWT story-level breakdown, the user can pair this with the `ba-user-story` skill afterward — offer at the end.

Format: short scenario name + Given/When/Then, OR numbered "the system shall..." statements if formal style is preferred.

*AC testability rule:* every AC MUST be testable as written. If a behavior depends on an undetermined mechanism (vendor capability, partner choice, third-party feature not yet decided), **DO NOT write it as AC** — it would be untestable. Instead, list it under "Open questions" framed as: "How will [X] work given [Y constraint]?" Once the mechanism is known, AC can be written. Shipping a non-testable AC like "mechanism phụ thuộc vendor — discuss để chốt" is a defect.

*AC ↔ BR linkage:* each AC should reference the BR(s) it tests, in parentheses after the AC title: "**AC-PAY-01 — Successful transfer** *(tests BR-01, BR-04)*". Each BR should have at least one AC, or be marked "*informational only — no behavior to test*" with reason. Orphan AC (no BR) or BR without AC are coverage gaps — see self-check Step 6.

**Non-functional (business level)** — SLA expectations in business terms ("user expects transfer to feel near-instant"), audit requirements, data sensitivity classification, compliance flags. **Do not** write technical NFRs ("1000 req/s", "RTO 4h") unless the user gave that number — that's typically dev or infra sizing decision. Express in business language ("tolerable downtime: 1 working day"), let dev translate to technical SLA.

**Data considerations** — what data is created/read/modified, sensitivity classification (PII, financial, sensitive personal), retention need from business POV ("transactions need 10-year retention per regulation X"), data ownership boundary. **No** schema, **no** column names, **no** field types.

**Dependencies** — bulleted: other systems, teams, prerequisites, blockers.

**Open questions vs Decisions handed to dev** — two distinct buckets, often confused:

| Bucket | What it is | Who decides | Format |
|---|---|---|---|
| **Open questions** | Business uncertainty needing a business answer (scope, policy, sequence, regulatory) | Business stakeholder (Finance, Risk, Compliance, leadership) | Question phrased; named answerer; deadline if known |
| **Decisions handed to dev** | Technical choice within a business constraint | Dev / architect | Statement of what's left; constraint to respect (BR-XX) |

When unsure where an item fits, ask: who can answer this? Business person → Open question. Dev/architect → Decisions handed. If both could weigh in, default to **Open question** (business gets first look; tech follows).

**Decisions handed to dev** — explicit list of technical decisions dev owns. This section is **required** if (a) the prompt was framed technically, or (b) the spec implies meaningful technical choices. See `references/examples.md` for canonical examples.

### Step 5 — Apply variant-specific extras

Read `references/variants.md` for the variant you detected. Add the variant-specific sections. Don't shoehorn — every section earns its place.

### Step 6 — Self-check before delivering

Run through this checklist. If anything fails, fix before showing the user.

**Structure & content:**
- Every section has substance, no placeholders
- Out-of-scope is explicit (saves arguments later)
- Business rules are numbered, one rule per item
- Flows show actors at business level only, no infrastructure
- AC are observable outcomes, not click-by-click test steps
- No technical decisions snuck in (no endpoints, schemas, library names) — anything technical the spec implies should be in "Decisions handed to dev", not in the body
- Variant-appropriate extra sections included
- Domain terms preserved from prompt, not "translated" to generic

**Rigor checks (NEW — added after test feedback):**

- **AC testability**: scan every AC. Each must be testable as written. Any AC that says "mechanism TBD" or "depends on [unknown]" or "to be determined" is NOT valid AC — move it to Open questions instead. No exceptions.

- **AC ↔ BR coverage**: list every BR. For each, identify the AC(s) testing it. If a BR has zero AC, either (a) add AC, or (b) mark the BR as "*informational only*" with reason. If an AC doesn't reference any BR, ensure it's testing a general flow concern and note this. No silent gaps.

- **TBD-with-owner**: scan every "TBD" in BRs, tables, and NFRs. Each TBD must have *both* an owner ("Risk team") and a deadline / trigger ("trước sprint planning", "before go-live"). TBD without owner → invalid, must be moved to Critical prerequisites or Open questions.

- **Number traceability**: scan every numeric value in Mermaid diagrams (windows, retries, durations, thresholds). Each must trace to either a BR or the assumption appendix. No "orphan numbers" in diagrams. If a number appears in both diagram and BR/appendix, the values must match exactly.

- **Critical prerequisites surfaced**: scan assumptions made. Any assumption whose falsity would invalidate the spec's structure (not just tune a parameter) must be in the "Critical prerequisites" section at the top of the spec, not buried in the appendix.

- **Glossary present when needed**: count distinct domain-specific terms used (acronyms, system-specific jargon like "plant code", "Sales Document", "condition record", "GTGT", "BR-XX cross-refs"). If ≥3 terms might be unfamiliar to a reader outside the immediate team (e.g., Finance reader for SAP terms, BA from another product for fintech terms), include a Glossary section. When in doubt, include it.

- **Open question vs dev decision boundary**: every item in "Open questions" must be answerable by a *business* person. Every item in "Decisions handed to dev" must be answerable by *dev/architect*. Re-classify any blurred items.

- **Every assumption captured for the appendix** — scan AC, BRs, and NFRs for inline numbers/thresholds/defaults you introduced and make sure they're on the list.

### Step 7 — Surface what's likely missing

Scan for typically-forgotten topics. List as "Open questions / Topics you may want to cover" — don't auto-write. Let the user decide.

Common omissions across variants:
- **Empty / first-use state** — what shows when user has no data?
- **Migration** — if existing users have data in old format, how is it handled?
- **Reverse flow** — refund, cancellation, rollback, undo
- **Multi-device** — what when same user is on two devices?
- **Localization** — multiple languages, locales, timezones, currencies?
- **Disabled state** — when is the feature off (maintenance, regulation, account state)?
- **Reporting / analytics** — what events need logging for BI?
- **CS / dispute handling** — how does customer support help when something goes wrong?

Variant-specific omissions are in `references/variants.md`.

### Step 8 — Output format

Default: Markdown in chat. The user previews, may ask for revisions, then often wants .docx.

If the user asks for a file ("xuất ra Word", "save as docx", "tạo file") or you sense the spec is final, offer to export. To export, read `/mnt/skills/public/docx/SKILL.md` first, then produce a `.docx` with: H1 = doc title, H2 = section, H3 = subsection. Tables for rules and data mapping. Mermaid stays as code blocks in markdown output; for docx, render as images if the docx skill supports it.

**Canonical output order (chat or docx):**

1. Document title + 1-line summary
2. Document metadata table — **include a `Variant rationale` row** explaining briefly why this variant was chosen ("C — both POS and ERP are internal systems, no external partner"). Saves reader scrolling to appendix to understand framing.
3. Critical prerequisites — *if any* (right after metadata, before main body). Highlight visually (callout box or bold heading) so they're impossible to miss.
4. Sections in the priority order from Step 3
5. "Open questions / Topics you may want to cover" (from Step 7)
6. "Decisions handed to dev" — when applicable
7. "Phụ lục: giả định cần xác nhận" (Step 9)

### Step 9 — Assumptions appendix (REQUIRED)

Same pattern as `ba-user-story` skill. Track every assumption — variant choice, scope decisions, numeric thresholds, default behaviors, sections you included or excluded — and consolidate at the end.

**Format:** table with columns *Giả định | Ảnh hưởng đến | Lý do mặc định*.

**Grouping order** (use these groups when ≥6 assumptions; pick what applies):

1. **Critical prerequisites** *(if any — also surfaced at top of doc; here is the consolidated list)* — flag visually (⚠ or bold "CRITICAL") because falsity invalidates spec structure
2. **Scope** — variant choice, what's in/out, currencies, geography, persona coverage
3. **Sync & timing** — windows, cutoffs, retry schedules, SLAs (especially for Variant C)
4. **Policy & rules** — thresholds, limits, default values, retention
5. **Behavioral defaults** — when multiple sensible options existed, what you picked

Always include "Variant chosen" as the first row of group 2 (Scope), with rationale.

End with a conversational prompt: "Bạn nói rõ cần đổi gì → mình cập nhật tài liệu tương ứng. Các Critical prerequisites cần xác nhận sớm trước khi đi tiếp."

---

## Compose with other BA skills

This skill produces document-level specs. After the spec is approved, offer:
- **Story-level breakdown with INVEST + GWT** → use `ba-user-story` skill ("Want me to break this into user stories for the dev team?")
- **Process diagrams beyond simple sequence** (full BPMN AS-IS / TO-BE) → `ba-process-model` skill (when built)
- **Test cases derived from AC** → `ba-test-case` skill (when built)

Don't auto-trigger these. Offer at the end of the output as a question, leave the call to the user.

---

## Language handling

Match the user's language. Preserve domain terms exactly as given — don't auto-translate.

For Vietnamese specs, prefer consistent Vietnamese section titles:

| English | Vietnamese |
|---|---|
| Context & objective | Bối cảnh & mục tiêu |
| Scope | Phạm vi |
| Critical prerequisites | Điều kiện tiên quyết cốt lõi |
| Actors | Tác nhân |
| Business rules | Quy tắc nghiệp vụ |
| Flows | Luồng nghiệp vụ |
| Acceptance criteria | Tiêu chí chấp nhận |
| Non-functional requirements | Yêu cầu phi chức năng |
| Data considerations | Yêu cầu về dữ liệu |
| Dependencies | Phụ thuộc |
| Decisions handed to dev | Quyết định chuyển giao dev |
| Open questions | Câu hỏi mở |
| Glossary | Thuật ngữ |
| Assumptions appendix | Phụ lục: giả định cần xác nhận |

Use these consistently within one document.

---

## Common anti-patterns

- **Technical phrasing leaking in** — "the API shall return JSON with field X" → rewrite as "the response provides [business meaning] to the caller"
- **Wall of unnumbered rules** — number them. Saves communication forever after.
- **Flow diagrams with infra components** (load balancers, queues, microservice X) — keep at actor + system box level only
- **Skipping out-of-scope** — always include, even if it feels obvious. Out-of-scope is where most spec arguments come from
- **AC as click-by-click test steps** ("click X, see Y") — describe observable outcomes regardless of UI navigation
- **Specifying technology choices** ("use REST", "use OAuth2", "use Postgres") — those are dev choices, surface in "Decisions handed to dev"
- **Implicit assumptions** ("of course we use VND") — capture in appendix even if "obvious"; obvious to BA ≠ obvious to dev or stakeholder

---

## See also

- `references/variants.md` — deep per-variant guidance: required sections, questions to ask, variant-specific pitfalls
- `references/examples.md` — annotated good vs bad spec excerpts, Mermaid templates for common flows, complete short spec example, full "Decisions handed to dev" example
