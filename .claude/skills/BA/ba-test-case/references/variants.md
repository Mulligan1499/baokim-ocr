# Test Case Variant Recipes

## V-A — Functional test cases from AC

**When this variant:** You have AC from a user story or feature spec and need to derive tests; team uses formal test management; QA needs traceable test inventory.

**Audience:** QA engineers (manual or as basis for automation), dev (sanity checks during dev), test lead.

**Required additional sections:**
- Coverage matrix (AC → TC mapping)
- Test data setup section (business-level descriptions)

**Variant-specific questions if AC is not yet provided:**
1. Can you provide AC from a user story or feature spec? (Recommend running `ba-user-story` or `ba-feature-spec` first for traceability)
2. If no AC available, proceed from feature description? (Output will be less precise)

**Variant-specific pitfalls:**
- Producing tests without coverage matrix — defeats the value of having AC as source
- Over-mapping (5+ test cases per simple AC) — usually means under-decomposing the AC
- Under-mapping (1 test case for a multi-condition AC) — missing boundaries / states
- Mixing positive and negative without clear labels

**Blind spots:**
- AC that mention "the system shall log X" — easy to skip because it's not user-visible; needs separate audit-log test
- AC implicit in BR (e.g., "BR-XX: idempotency at business level") — may not have direct AC but still needs test

**Typical length:** 5-30 test cases per feature (depending on AC count).

---

## V-B — UAT scenarios

**When this variant:** Pre-release UAT phase; need business users to validate workflows; phase gate for go-live; stakeholder demo / sign-off.

**Audience:** Business users (the real or proxy customers), product owners, leadership signing off.

**Required additional sections:**
- Persona list (3-5 realistic personas covering target segments)
- End-to-end business workflow definition (what business outcome each scenario achieves)

**Variant-specific questions:**
1. Who are the UAT testers? (Real users? Proxy users? Internal staff acting as users?)
2. What's the UAT environment? (Production-like sandbox? Pre-production?)
3. What's the sign-off criteria? (% scenarios passing? Specific scenarios mandatory pass?)
4. Duration of UAT phase? (Affects scenario depth)

**Variant-specific pitfalls:**
- UAT scenarios at functional-test granularity (too detailed for business users)
- Scenarios that test technical edge cases (not UAT's job — that's QA)
- Forgetting failure scenarios (UAT should include "what happens when something goes wrong")
- Personas too abstract ("a user") vs realistic ("Mai, 28, lần đầu dùng app")

**Blind spots:**
- Cross-feature integrations — UAT should test workflows that span multiple features the dev team built in isolation
- Real-world data shape — sandbox often has clean data; real UAT needs messy realistic data
- Time-based scenarios — month-end, end-of-day, holiday handling

**Typical length:** 5-15 UAT scenarios per release.

---

## V-C — Negative / edge cases

**When this variant:** Functional test cases exist (V-A) but coverage of negative paths is thin; security/robustness focus; pre-prod hardening.

**Audience:** QA engineers (running the additional tests), security review (for adversarial cases).

**Required additional sections:**
- Edge case category coverage matrix (which categories tested, which not)

**Variant-specific questions:**
1. What V-A tests already exist? (Don't duplicate happy path)
2. Specific risk areas to emphasize? (Money flows? Authentication? Data integrity?)
3. Include security/adversarial cases? (Or just normal negative cases?)

**Variant-specific pitfalls:**
- Testing every conceivable bad input — leads to test bloat; focus on business-meaningful negatives
- Missing recovery scenarios (after a failure, can user retry / cancel cleanly?)
- Treating all edges as equal priority (some failures are catastrophic, some are minor UX)

**Blind spots:**
- Concurrency (especially double-submit) — easy to forget in synchronous-flow thinking
- Time-of-day / day-of-week (after cutoff, during maintenance, on holidays)
- Multi-language / encoding edge cases (Vietnamese characters, emoji, special punctuation)

**Typical length:** 10-30 additional test cases focusing on negatives, complementing V-A.

---

## When to use which (or all)

| Situation | Variants |
|---|---|
| Sprint completing a story, need tests | V-A |
| Feature complete, need full QA coverage | V-A + V-C |
| Pre-release, need business sign-off | V-B (and V-A should already exist) |
| Security review prep | V-C with adversarial framing |
| Test plan for a whole feature | V-A (from AC) + V-B (UAT) — V-C optional if regulatory or high-risk |
