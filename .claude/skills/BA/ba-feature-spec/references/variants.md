# Variant Recipes

Detailed guidance per variant. Read only the section matching the variant you detected in Step 0.

---

## Variant A — Internal feature spec

**Audience:** internal dev + QA + product owners.

**Tone:** functional, structured. Assumes reader has product context.

### Required additional sections (beyond universal)
None — universal sections cover this variant.

### Highly recommended sections
- **Empty / first-use state** — what users see before they have any data in the feature
- **Migration behavior** — if existing users have legacy data or behavior, how is it handled
- **Analytics events** — what user actions need logging for BI (event name + key properties, business-level — not implementation)

### Variant-specific questions if context is thin
1. Entirely new functionality or modifying an existing one?
2. Which platforms — mobile, web, both?
3. Any deadline or business event driving timing? (regulatory date, marketing campaign, partner launch)

### Variant-specific pitfalls
- Treating the spec as a feature list rather than a story with business framing
- Forgetting the empty/first-use state (very common with new features)
- Forgetting the migration path when changing existing behavior
- Skipping the "why now" — business context that helps dev make good micro-decisions

### Typical length
Short feature: 2-4 pages. Medium: 5-8 pages. If approaching 12+ pages, consider splitting into a BRD + multiple FRDs.

---

## Variant B — Partner-facing API spec

**Audience:** internal dev (who build it) + the partner organization (who consume it). The spec needs to be useful for the partner's dev team to understand what they can do, while still being BA-level (not dev-design).

**Tone:** clear, slightly more formal, contract-aware. The partner is a different organization with different incentives — be precise about expectations.

### Required additional sections
- **Partner onboarding flow** — business-level steps for a new partner: how they get credentials, what sandbox access looks like, what gates exist before production access. Don't specify the technical mechanism (key vs OAuth — dev chooses); specify the business flow (request → review → sandbox → KYC partner → production).
- **Partner permissions & quotas** — at business level: which partners can perform which operations, what tier system exists (if any), the existence of rate limits (dev sizes the numbers within business intent). Use a table mapping partner type → permitted operations.
- **Webhook / callback expectations** — when does the platform notify the partner of events? What events? Retry expectation in business terms ("we make best-effort delivery for 24 hours, then mark the event as expired in our log").
- **Reconciliation** — how partner reconciles their records with ours: daily summary export, paginated query endpoint, webhook of state changes? Specify the business need; dev picks the delivery mechanism.
- **Sandbox vs production parity** — what business behaviors partners can rely on in sandbox: same rules? Reduced limits? Faked external integrations? This matters for partner testing.
- **Partner SLA expectations** — uptime expectation we communicate to partners, response time expectation, planned maintenance communication policy.

### Variant-specific questions if context is thin
1. What kind of partner — merchant, bank, fintech, generic third-party developer?
2. One specific partner or a partner program (many partners)?
3. What business operations does the partner need to perform on our system?
4. Does the partner notify us back (webhooks), or only consume (read/query)?
5. Is there a sandbox environment requirement?

### Variant-specific pitfalls
- Drifting into technical API design (endpoint shape, schema, auth mechanism) — that's dev's
- Forgetting partner-side error visibility — what does the partner see when something fails on our side? Business answer: "partner receives a clear error category and a reference ID so they can contact us"
- Forgetting cross-partner data isolation expectations — partner A must not see partner B's data even in shared reports
- Skipping the partner offboarding flow — what happens when partner relationship ends? Are their credentials revoked, data archived, etc.
- Specifying technology ("REST/JSON over HTTPS") — that's dev. Specify business need ("partner integrates programmatically; protocol is dev's choice within industry standard").

### Section to ALWAYS include for this variant
**Partner-facing error categories** — list the error categories partner needs to distinguish (e.g., "insufficient permissions", "data not found", "rate limit exceeded", "validation failed", "internal system unavailable"). Dev picks specific codes/messages within these categories.

### Typical length
6-15 pages. Partner specs are longer than internal because they carry more contract weight.

---

## Variant C — System-to-system integration spec

**Audience:** internal dev + ops + the owner team of the other system. Often the "other system" is internal (another team's service) but sometimes external (an external SaaS, a government system, a banking core).

**Tone:** precise about data and ownership. Integration specs live and die by data clarity.

### Required additional sections
- **Data mapping** — a table mapping fields/concepts from source to target with business meaning, not technical fields. Columns: source concept | target concept | transformation rule (business-level, e.g., "uppercase", "map A→1, B→2", "concatenate with prefix") | notes / edge cases.
- **Sync / async expectation** — why this needs to be synchronous (or why async is acceptable), from business POV: "user is waiting on the screen for result" = sync need; "data feeds a nightly report" = batch async is fine. State the BUSINESS need; dev picks implementation.
- **Failure & retry expectations** — what happens when the other system is down or slow? Queue and retry? Skip and alert? Failover to manual process? State the business expectation, not the retry mechanism. Include: how long can we tolerate the other side being down before business impact starts?
- **Data ownership & trust boundary** — who is the source of truth for each piece of data? When data conflicts (our record says X, their record says Y), whose wins and why?
- **Initial data load / backfill** — when the integration goes live, do we need historical data backfilled? From what date? Via what mechanism (business-level — full sync, incremental, manual import)?

### Variant-specific questions if context is thin
1. Which system on the other side? Direction — we push, we pull, or both?
2. Real-time or batch? (business need — not implementation)
3. Is the other system internal (we can coordinate releases) or external (we must accept their pace)?
4. Is there historical data to backfill, or only forward data?
5. Who is source of truth for shared entities?

### Variant-specific pitfalls
- Specifying transport protocol (REST, SOAP, file drop, message queue) — that's dev. Specify business need (latency, volume, security level).
- Skipping the trust boundary discussion — leads to "their record says X, our record says Y, which is right?" arguments later.
- Forgetting backfill — integration goes live, but historical data isn't synced, and reports are wrong for the first month.
- Treating the other system as infallible — always include "what if their data is wrong / stale / missing fields we expect".

### Section to ALWAYS include for this variant
**Monitoring & alerting expectations (business level)** — what does the business need to know? "Failed sync rate > 1% triggers alert", "Daily reconciliation mismatch triggers a ticket". Don't specify alerting tool — specify business signals.

### Typical length
5-12 pages.

---

## Variant D — BRD (Business Requirements Document)

**Audience:** executives, sponsors, stakeholders, sometimes board.

**Tone:** business-narrative, decision-supporting. The reader is making a go/no-go or approval decision, not building anything.

### Required additional sections
- **Business case** — problem statement, opportunity sizing (qualitative or quantitative), current state cost / risk if we don't act, future state value. 1-2 pages.
- **Success metrics** — measurable outcomes. Each metric should have: name, baseline (current), target (future), measurement method, timeframe. Format as a table.
- **Stakeholders** — primary and secondary, each with their concern / what they care about. Format as a table.
- **Key business risks** — business risks (regulatory, reputational, customer adoption, competitive), NOT technical risks. For each: description, likelihood, impact, mitigation approach.
- **Decision being requested** — explicit at the top: "We're requesting approval for X by date Y to enable outcome Z."

### Shifted emphasis (vs other variants)
- AC become summary-level, not detailed. BRD says "users can transfer to phone numbers under their tier limit" — detailed AC live in a follow-up FRD.
- No detailed flows unless they affect the business decision.
- Less data-considerations detail — keep at "this involves customer financial data" rather than detailed sensitivity classification.
- More numbers — sizing, costs, projected impact. If you don't have numbers from the user, mark as TBD and ask in the appendix.

### Variant-specific questions if context is thin
1. Who is the audience? (specific role/level — exec sponsor, finance committee, product council)
2. What decision does this doc support? (budget approval, scope sign-off, go/no-go, vendor selection)
3. What's the timeline for the decision?
4. What success metrics matter? Do they already have targets in mind, or do we propose?

### Variant-specific pitfalls
- Writing an FRD and calling it a BRD — exec readers want decision support, not implementation detail
- Hand-waving on business case — if asked for ROI, give qualitative reasoning even when numbers aren't available
- Forgetting the explicit "decision requested" — readers shouldn't have to infer what they're being asked to approve
- Long unstructured prose — execs scan. Use tables, headings, summary boxes

### Section to ALWAYS include for this variant
**Executive summary** at the very top — one paragraph (5-8 sentences) covering: what we're doing, why now, what we need, what success looks like.

### Typical length
4-10 pages. Anything longer for a BRD risks not being read.

---

## When you need more than one variant

Some requests span variants. For example, "build a partner API that lets fintech apps create a wallet for their users" is Variant B (partner API) but might warrant a BRD upstream if the business case isn't established.

In that case:
- Choose the **most immediate** variant (what is the user asking you to write right now?) 
- Note in the appendix: "If you also need a BRD for executive approval, I can produce one separately — let me know."
- Don't blend variants in one document. BRDs and FRDs/API specs serve different readers; mixing them dilutes both.
