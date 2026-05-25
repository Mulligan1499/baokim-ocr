# Annotated Examples — Good vs Bad User Stories

Each example shows a flawed version, then a better version, with notes on what changed and why.

---

## Example 1 — Login

### Bad

> As a user, I want to log in so that I can use the app.

What's wrong:
- Generic persona ("user")
- Tautological value ("so I can use the app")
- No AC — not testable, no INVEST

### Better

> **Story:** Phone-number login for returning retail customer
>
> **As a** registered retail customer who already has an account
> **I want** to log in using my phone number and password
> **So that** I can access my balance and recent transactions without re-registering
>
> **AC1 — Successful login**
> - Given I have an active account with a verified phone number
> - When I enter my correct phone number and password
> - Then I see the home screen with my balance and last 5 transactions
>
> **AC2 — Wrong password**
> - Given I have an active account
> - When I enter my phone number and an incorrect password
> - Then I see a message "Sai mật khẩu" that does not reveal whether the phone number exists in the system
> - And the failed attempt is counted against the lockout threshold
>
> **AC3 — Account locked**
> - Given I have failed 5 login attempts in the last 15 minutes
> - When I attempt to log in again with any password
> - Then I am blocked from login for 30 minutes
> - And I see a message showing the unlock time and a "forgot password" CTA
>
> **AC4 — Inactive account**
> - Given my account is suspended (KYC pending or admin action)
> - When I enter correct credentials
> - Then I see a message explaining the suspension reason
> - And I see a "contact support" CTA

Notes on what changed:
- Persona is specific: returning, verified, retail customer
- Value is observable (sees balance and transactions)
- AC2 captures a subtle compliance point: don't reveal account existence
- AC3 covers the lockout rule
- AC4 covers a state-based edge case
- Nothing in the AC references HTTP endpoints, password hashing, or session token format — those stay with dev

---

## Example 2 — Transfer to phone number

### Bad

> As a customer, I want to transfer money to a phone number, so that I can pay my friend.
>
> AC: User clicks transfer, enters phone number, enters amount, clicks confirm, money is transferred.

What's wrong:
- AC reads like test steps (click, enter, click)
- No edge cases (insufficient balance? unregistered recipient? limit exceeded?)
- No business rules surfaced
- "Pay my friend" is too narrow — what about merchant, family, self-transfer?

### Better

> **Story:** Transfer to a phone number (registered recipient)
>
> **As a** verified retail customer with an active wallet
> **I want** to transfer money to another wallet user by their phone number
> **So that** I can pay individuals (friends, family, vendors) without needing their account number
>
> **AC1 — Successful transfer to registered recipient**
> - Given the recipient phone number is associated with an active verified wallet
> - And my balance is sufficient (amount + fee, if any)
> - And the amount is within my daily and per-transaction limit
> - When I confirm the transfer
> - Then the amount is deducted from my balance and credited to the recipient
> - And I receive an in-app notification and SMS confirmation with a reference number
> - And the recipient receives an in-app notification and SMS
> - And the transaction appears in both parties' history with timestamp and reference
>
> **AC2 — Insufficient balance**
> - Given my balance is less than (amount + fee)
> - When I attempt to confirm
> - Then the transfer is rejected before any money moves
> - And I see a clear message with my current balance and a "top-up" CTA
>
> **AC3 — Daily limit exceeded**
> - Given my cumulative transferred amount today plus this amount would exceed my tier's daily limit
> - When I attempt to confirm
> - Then the transfer is rejected
> - And I see the remaining daily limit and the time the limit resets
>
> **AC4 — Suspicious activity step-up**
> - Given the fraud system flags this transaction as suspicious (e.g., new recipient and large amount)
> - When I attempt to confirm
> - Then the transaction is held for verification
> - And I am prompted for additional authentication
> - And only after the additional authentication succeeds does the transfer complete

Notes:
- AC1 captures the happy path AND auxiliary observable outcomes (notifications, history entries) — these matter to BA because they affect what dev needs to build
- AC2 specifies "before any money moves" — that's a business rule about transactional safety, BA owns this intent
- AC3 surfaces the limit rule including the reset behavior
- AC4 surfaces a compliance / fraud concern at the business level (when to step up) without specifying the OTP delivery channel or fraud algorithm

---

## Example 3 — A spec that crossed the line into dev

### Bad (overreach into dev)

> **AC:** Given the user submits the form, when the request hits POST /api/v1/transfer, then the system validates the JWT token, queries the users table to check balance, calls the limit-check microservice via gRPC, and returns HTTP 200 with a JSON response containing transactionId.

What's wrong (everything technical here is dev's call):
- Endpoint URL and method
- Authentication mechanism (JWT)
- Database table reference
- Microservice protocol (gRPC)
- HTTP status code and response schema

### Right scope

> **AC:** Given the user is authenticated and the recipient is valid, when the transfer is confirmed, then the user's balance reflects the new amount and a transaction record exists that can be retrieved later by both parties with a reference number.

The "how" of authentication, the storage mechanism, the protocol, the response shape — all dev's choice.

---

## Example 4 — "Decisions handed to dev" section

Sometimes the user gives a request that's framed technically but really wants a BA spec. After writing the stories and AC, append a section like this to make the BA / dev boundary explicit:

> **Decisions handed to dev**
>
> The following technical choices are left to the dev team — please flag any of these back to BA if a decision has business impact we should weigh in on:
>
> - API protocol (REST / GraphQL / gRPC) and endpoint structure
> - Authentication mechanism for partner calls (we require it to be industry-standard; specific choice is dev's)
> - Database schema and indexing
> - Caching and rate-limit implementation
> - Error code structure (we specify which scenarios need distinguishable errors from the partner's POV — see AC; the exact codes / messages are dev's)
> - Idempotency mechanism (we require idempotency for POST operations — see AC; the implementation, e.g. idempotency-key header, is dev's)

This pattern is especially useful when the original prompt sounds like "design the API for X" but the right BA response is a feature spec, not an API spec.

---

## Example 5 — Persona specificity gradient

Same feature, persona getting progressively better:

- ❌ "As a user"
- 😐 "As a customer"
- 🙂 "As a registered customer"
- 😊 "As a registered retail customer with a verified phone number"
- ⭐ "As a registered retail customer with a verified phone number whose wallet has been active for ≥ 30 days"

The right level depends on whether the extra qualifiers affect AC. If the "30 days active" thing changes a business rule (e.g., new users have lower limits), it belongs in the persona. If not, omit.

Don't add qualifiers for decoration. Each qualifier should change something downstream.
