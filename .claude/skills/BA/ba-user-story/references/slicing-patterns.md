# Story Slicing Patterns

When a request describes more than one story's worth of work, pick a slicing pattern. Each has trade-offs — pick the one that lets the team deliver and demo value in steps.

## Pattern 1 — By workflow step

Split along the user's journey. Each story is one step.

**Use when:** the feature is a multi-step process where intermediate steps have demonstrable value.

**Example — "Customer registers for the app":**
- Story 1: Customer enters phone number and receives OTP
- Story 2: Customer verifies OTP and creates password
- Story 3: Customer completes profile (name, DOB, address)
- Story 4: Customer submits ID for KYC review

Each story is independently testable. Story 1 can be demo'd before Story 2 exists.

**Caution:** don't slice so fine that a story has no business value alone. "Customer clicks submit button" is not a story — it's a UI interaction.

---

## Pattern 2 — By persona / role

Split when different actors do different things with the same feature.

**Use when:** the feature touches multiple roles with different concerns.

**Example — "Refund handling":**
- Story A: As a customer, I request a refund for an order
- Story B: As a customer service agent, I review and approve / reject refund requests
- Story C: As a finance officer, I see daily refund summary for reconciliation
- Story D: As the system, I notify the customer when refund status changes

**Caution:** system-as-actor stories are real and easy to forget. If there's automation, a scheduled job, or a webhook, give it a story.

---

## Pattern 3 — By business rule variation

Split when the same action behaves differently based on a rule.

**Use when:** there are eligibility tiers, customer segments, regulatory regimes that change behavior meaningfully.

**Example — "Transfer money":**
- Story A: As a basic-tier verified customer, I transfer up to 50M VND per day
- Story B: As a premium-tier verified customer, I transfer up to 500M VND per day
- Story C: As a corporate customer, I transfer with two-person approval if amount > 100M

**Caution:** don't proliferate stories for trivial rule differences. If rules only change a config value (limit threshold), one story with a rule table in AC is enough.

---

## Pattern 4 — By data variation

Split when the action operates on genuinely different data shapes or sources.

**Use when:** same intent, different data origins with different business handling.

**Example — "Bulk import contacts":**
- Story A: Import from CSV file
- Story B: Import from Excel file
- Story C: Import from Google Contacts via OAuth
- Story D: Import by manual paste

**Caution:** this pattern can over-proliferate. Prefer it when each variation has genuinely different business rules — not just file-format differences (those might be one story for dev).

---

## Pattern 5 — Happy path first, then edges

Split by completeness rather than scope.

**Use when:** feature is well-defined but you want to ship value quickly and add resilience later.

**Example — "Send money to phone number":**
- Story 1: Happy path — send to a registered phone number with sufficient balance
- Story 2: Send to unregistered phone number (pending claim flow)
- Story 3: Handle insufficient balance with clear messaging and top-up CTA
- Story 4: Handle daily limit exceeded with appeal flow
- Story 5: Handle blocked / suspended recipient

**Caution:** only use if the happy path alone delivers real value (some users can actually use it). If the happy path is unusable without edge cases, this pattern fails.

---

## Combining patterns

Real epics often need two patterns. Example: "Partner API for transaction reports" might be sliced by **persona** (which partner tier) AND by **workflow** (auth → query → download → reconcile). Produce a small 2D matrix and prioritize the highest-value cells first.

---

## How to choose — ask in order

1. Can the user / business demo value at each workflow step? → **Pattern 1**
2. Are there multiple actors with different concerns? → **Pattern 2**
3. Are there eligibility tiers / segment-specific rules? → **Pattern 3**
4. Are there genuinely different data sources or shapes? → **Pattern 4**
5. None of the above, but spec is huge? → **Pattern 5** (happy path first)

Default if unsure: **Pattern 1** (workflow step) — it's the safest and the most demo-friendly.
