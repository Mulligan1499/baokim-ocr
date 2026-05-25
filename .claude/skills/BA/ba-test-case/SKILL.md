---
name: ba-test-case
description: Generate test cases and UAT scenarios from acceptance criteria for QA, dev, and business users. Use whenever the user wants to write test cases, create test scenarios, derive tests from AC / requirements, or build a UAT plan. Triggers on phrases like "viết test case", "test cases cho", "kịch bản kiểm thử", "UAT scenarios", "test scenarios", "derive tests", "tạo test từ AC", "test plan". Produces functional test cases (from AC), negative / edge test cases, or UAT scenarios (end-to-end business workflows). Each test case includes ID, scenario name, related AC / BR, preconditions, test steps (numbered), expected results, postconditions, priority, and notes. Stays at business behavior level — describes WHAT should be tested observably, not HOW to automate (no UI selectors, code, framework-specific scripts). Uses a compose pattern that ingests AC from ba-user-story or ba-feature-spec output to produce traceable test cases mapped to source AC.
---

# BA Test Case

Generate test cases and UAT scenarios at BA level — describing **what** to test from a business perspective, with full traceability back to the AC or business rule the test exercises. This skill explicitly composes with `ba-user-story` and `ba-feature-spec` outputs.

## The BA boundary for test cases

A BA test case describes what to test, what conditions must hold beforehand, what steps the tester takes, what they observe, and what state they expect afterward — at **business behavior level**.

**Cover:**
- Scenario described in business language
- Preconditions in observable business terms
- Test steps as user / business actions (no UI selectors or code)
- Expected results as observable outcomes
- Mapping back to AC or business rule being tested
- Priority and rationale
- Test data needed at business level (e.g., "a verified retail customer with balance > 100K", not specific row in DB)

**Do NOT cover:**
- UI selectors (`#submit-btn`, XPaths)
- Code, scripts, automation frameworks (no Cypress / Selenium / Playwright syntax)
- API endpoint specifics (no `POST /api/v1/...`)
- Database queries or assertions
- Specific test data values that require DB knowledge (use business descriptions)

**Acid test:** can a manual tester or business user perform this test without dev/QA-engineer knowledge? Yes → right level. If they need to know UI internals or DB, you've drifted.

---

## Compose pattern — this skill's specialty

This is the first BA skill that **ingests output from other skills** as input. When the user provides AC (acceptance criteria) from a prior `ba-user-story` or `ba-feature-spec` output, the skill:

1. **Parse** the AC text — identify each AC by ID (e.g., AC-01, AC-PAY-01), its Given-When-Then structure, the BR it references
2. **Map** AC → test cases at 1:N ratio (typically 1-4 test cases per AC: happy + edge + negative)
3. **Trace** every test case back to source AC ID in a "Related AC" column
4. **Surface** AC without coverage and AC with redundant coverage in the self-check

**If user does NOT provide AC** (only a feature description), this skill can still produce test cases but:
- The output is less precise (no traceability)
- Recommend at the start: "Để có coverage tốt hơn, nên có AC từ `ba-user-story` hoặc `ba-feature-spec` trước. Tôi vẫn có thể tạo test scenarios từ mô tả feature; nói rõ nếu muốn dùng cách đó."

When AC is provided, the appendix records: "Source AC: [from skill X, document Y]" so traceability is preserved across the chain.

---

## Step 0 — Detect variant

| Variant | Signals | Output |
|---|---|---|
| **V-A — Functional test cases from AC** (default) | "test cases cho", AC provided, mapping framing | Test cases mapped 1:N to AC, positive + happy path |
| **V-B — UAT scenarios** | "UAT", "end-to-end test", "business acceptance scenario" | End-to-end scenarios at higher level, ~3-10 per feature |
| **V-C — Negative / edge cases** | "negative testing", "edge cases", "boundary testing", explicit negative framing | Focused list of negative + boundary cases, beyond happy path |

Variants can compose — V-A + V-C for thorough coverage; V-B alone for UAT phase.

Record variant + rationale in metadata.

---

## Workflow

### Step 1 — Detect AC presence + read context

**First action:** scan the user's prompt + any attached context for AC. Signals:
- "AC-01", "AC-PAY-01" identifier patterns
- "Given... When... Then..." structure
- Numbered AC sections from prior outputs
- Reference to a prior chat ("the user story we wrote earlier")

If AC is detected:
- Parse each AC: ID, name, Given/When/Then, related BR
- Confirm in metadata: "Source: [where AC came from]"
- Proceed at high precision (full traceability)

If AC is NOT detected:
- Warn user once at the start (see Compose pattern section)
- Ask: "Do you want to (a) provide AC first, or (b) proceed with test scenarios from feature description?"
- If (b): proceed but mark traceability as "no source AC" in appendix

Universal context to read (regardless of AC presence):
1. What feature / functionality is being tested?
2. Who is the target tester? (QA professional / business user for UAT / dev for unit-level — affects detail level)
3. Test environment context — staging? production-like sandbox? (affects what test data assumptions are reasonable)
4. Priority guidance? (P0/P1/P2 thresholds — what's critical?)

### Step 2 — Determine sections

Universal:
- Metadata (variant, variant rationale, source AC reference if applicable, target tester, environment, owner, status, date)
- Critical prerequisites (if any)
- Test cases (organized in a table; details per test case)
- Coverage matrix (AC → test cases mapping; required when AC is the source)
- Open questions
- Assumptions appendix

Conditional:
- Glossary if domain terms unfamiliar to target tester
- Test data setup section (business-level descriptions, not DB seeds)

### Step 3 — Generate test cases per AC (V-A)

For each AC:

1. **Happy path test case** — exercise the Given/When/Then exactly as written; this is the basic confirmation that the AC's main assertion holds
2. **Boundary tests** — if AC mentions limits/numbers, test at boundary (limit-1, limit, limit+1)
3. **Negative tests** — if AC has a rule, test what happens when rule violated (separate AC or this one's failure path)
4. **State variation tests** — if AC depends on entity state (active/locked/expired), test other states

**Typical ratio:** 1 AC → 2-4 test cases. AC with rules and multiple conditions → more tests. Simple AC → 1-2.

For each test case, write:

| Field | Format |
|---|---|
| ID | TC-[area]-[number], e.g., TC-PAY-001 |
| Name | Short scenario description, verb-phrase ("Transfer success to registered recipient") |
| Related AC | Source AC ID(s), e.g., "AC-PAY-01" |
| Related BR | BR ID(s) being indirectly tested, e.g., "BR-01, BR-04" |
| Priority | P0 (critical path) / P1 (important) / P2 (nice to have) — with one-line rationale in Notes |
| Preconditions | Business-state requirements, e.g., "Verified retail customer with balance > 100K VND" |
| Test data | Business-level description, e.g., "Customer with tier=basic, daily-limit=50M VND, current-balance=5M VND" |
| Steps | Numbered, each step is one user/system action observable to tester |
| Expected result | What tester should observe, at business level |
| Postconditions | State assertions after test, in business terms |
| Notes | Edge case rationale, automation potential, manual-only reasons |

### Step 4 — Step writing rules

Each step is one observable action. Step format:

> `<number>. <Actor> <verb-object>`

Examples (good):
> 1. Tester logs in as a verified retail customer
> 2. Tester navigates to Transfer screen
> 3. Tester enters recipient phone number "0901234567"
> 4. Tester enters amount 100,000 VND
> 5. Tester confirms the transfer

Examples (bad — drifting):
> 1. Tester opens browser to `https://app.example.com/login` ← URL drift
> 2. Tester clicks element `#login-btn-primary` ← selector drift
> 3. POST `/api/v1/transfer` with payload `{...}` ← API drift
> 4. Verify DB row in `transactions` table ← DB drift

When tests need specific data values (like the phone number "0901234567"), use **realistic placeholders** that read naturally for manual testers. For automation, the team translates these to test fixtures — that translation is QA engineer's job, not BA's.

### Step 5 — Expected results

Expected results describe what tester observes at business level. Multiple observations are common — list them.

Example (good):
> **Expected result:**
> - Tester sees a success message with the transaction reference number
> - Tester's balance decreases by 100,000 VND (visible on home screen)
> - Recipient receives an SMS notification within 5 seconds (verify by checking recipient's phone)
> - Transaction appears in tester's history with timestamp and reference

Example (bad):
> Expected: API returns 200 OK with `{"status": "success"}`. DB transactions table has new row.

The first is testable by a human at business level. The second requires dev/QA-engineer access.

### Step 6 — Coverage matrix

When AC is the source, produce a coverage matrix table:

| AC ID | AC Description (short) | Test case IDs covering it | Coverage status |
|---|---|---|---|
| AC-PAY-01 | Successful transfer | TC-PAY-001, TC-PAY-002, TC-PAY-003 | ✅ Covered (3 cases) |
| AC-PAY-02 | Insufficient balance | TC-PAY-004 | ✅ Covered (1 case) |
| AC-PAY-03 | Daily limit exceeded | — | ⚠ NOT YET — needs TC |

**Coverage rule:** every AC should have ≥1 test case. AC with multiple Given/Then variations should have multiple test cases.

**Redundancy check:** if 3+ test cases cover the same AC without testing meaningfully different conditions, prune.

### Step 7 — UAT scenarios (V-B)

UAT scenarios are different in nature:
- **End-to-end** — span multiple features, complete a business outcome
- **Realistic personas** — written as if a real user is doing real work
- **Less granular** — each step might encompass multiple system actions
- **Outcome-focused** — what business goal is achieved, not technical conditions

Format:

| Field | Format |
|---|---|
| ID | UAT-[area]-[number] |
| Scenario name | Business workflow name, e.g., "New customer registers and makes first transfer" |
| Persona | Realistic user description ("Mai, 28, lần đầu dùng ví điện tử, có 500K trong tài khoản ngân hàng") |
| Business goal | Outcome the persona is trying to achieve |
| Pre-state | What's true about the system before |
| Steps | Higher-level than functional TC: "Mai opens the app", "Mai completes registration", "Mai links bank account", "Mai transfers 100K to a friend" |
| Success criteria | Business-level: "Mai successfully transfers; friend receives notification; both have correct balances" |
| Failure indicators | What signals UAT failure (not necessarily a bug — could be UX issue, confusing flow) |

Aim for 5-10 UAT scenarios covering: happy path, common variations, key error recovery, the 2-3 most important business outcomes.

### Step 8 — Negative / edge cases (V-C)

Focus on what V-A might under-cover. Categories to brainstorm:

- **Boundary values** — min, max, zero, empty, very large
- **Format violations** — wrong type, special characters, encoding
- **State conditions** — entity in wrong state for the action (locked, expired, pending)
- **Permission violations** — actor lacks the right role/scope
- **Concurrency** — simultaneous actions on same entity (e.g., double-tap submit)
- **Timing** — actions outside business hours, after cutoff, during maintenance
- **External dependency failures** — what if external service is down/slow?
- **Data integrity** — stale data, conflicting concurrent updates
- **Malicious inputs** — SQL-injection-style strings, XSS attempts (test that they're handled, not bypass)
- **Recovery** — after a failure, can user retry / cancel / get back to a sane state?

For each category, ask: does at least one test case cover this?

### Step 9 — Self-check (rigor)

**Coverage rigor:**
- Every AC has ≥1 test case (V-A)
- Every test case traces to ≥1 AC or BR (no orphan test cases — if a TC has no source AC, mark it as exploratory/protective with rationale)
- Negative / edge category coverage (V-A): at minimum 1 test from each of: boundary, state, permission, concurrency
- UAT scenarios cover the 2-3 most important business outcomes (V-B)

**Step rigor:**
- Every step is observable by a manual tester at business level
- No URL/selector/API/DB drift
- Steps form a complete sequence that achieves the test scenario
- Each step has clear actor (Tester / System / External party)

**Expected result rigor:**
- Multiple observations listed where relevant (not just one outcome)
- Observations are business-level, not technical
- Postconditions distinct from expected result (postcondition = persistent state assertion)

**Boundary check:**
- No automation framework syntax
- No code or pseudo-code
- No DB queries
- Test data described in business terms (TBD-with-owner if needed)

**Traceability rigor:**
- Coverage matrix included when AC is source
- Source AC referenced in metadata
- Any "orphan" test cases (no source AC) explained

### Step 10 — Output

Default: Markdown in chat. For many test cases, summary table at top + details below.

For file: read `/mnt/skills/public/docx/SKILL.md` first; produce .docx with: H1 = test plan title, H2 = areas (or AC groups), H3 = each test case.

**Canonical output order:**
1. Document title + 1-line scope
2. Metadata (variant, variant rationale, source AC reference, target tester, environment, status, date)
3. Critical prerequisites (if any)
4. Test data setup (business-level descriptions, common across test cases)
5. Coverage matrix (V-A) / Persona list (V-B) / Edge case category coverage (V-C)
6. Test cases / UAT scenarios — table summary then per-case detail
7. Open questions
8. Assumptions appendix

### Step 11 — Assumptions appendix

Same pattern. Group:
1. **Critical prerequisites** (flagged)
2. **Scope** — variant choice, AC source (if any), what's in/out of test scope
3. **Coverage choices** — which AC over-covered, which paths skipped as low-risk
4. **Test data assumptions** — placeholder values used, default personas
5. **Priority calibration** — how P0/P1/P2 were assigned

---

## Compose with other BA skills

This skill ingests from `ba-user-story` and `ba-feature-spec`. After producing tests, offer:
- **Process flow showing test execution sequence** (rarely needed but useful for complex UAT) → `ba-process-model`
- **Data needed for tests catalogued** → `ba-data-spec` Variant B

---

## Language handling

Vietnamese canonical:

| English | Vietnamese |
|---|---|
| Test case | Trường hợp kiểm thử |
| Scenario | Kịch bản |
| Preconditions | Điều kiện tiên quyết |
| Test steps | Các bước kiểm thử |
| Expected result | Kết quả mong đợi |
| Postconditions | Điều kiện sau kiểm thử |
| Coverage matrix | Ma trận bao phủ |
| UAT scenario | Kịch bản UAT |
| Negative test | Kiểm thử trường hợp ngoài luồng |

---

## Common anti-patterns

- **Test step with URL/selector/API/DB** — drifts to automation/dev territory
- **One test case for a complex AC with many conditions** — split into multiple
- **No traceability** — test cases without source AC reference (when AC exists)
- **Only happy path** — every feature needs negative coverage
- **Expected result too vague** ("Test passes") — be specific about observations
- **Test data assumed without description** ("a customer") — describe the business state precisely
- **UAT scenarios at functional-test granularity** — UAT is business workflow, not technical test
- **Skipping coverage matrix** when AC exists — defeats the compose pattern's value

## See also

- `references/variants.md` — V-A vs V-B vs V-C details
- `references/examples.md` — annotated examples: good/bad test case, full AC-to-TC mapping example, sample UAT scenario
