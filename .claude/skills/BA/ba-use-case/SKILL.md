---
name: ba-use-case
description: Generate formal use cases for business analysts — Cockburn-style structured documents with named flows (main success, alternative, exception) for enterprise / formal deliverables, distinct from agile user stories. Use whenever the user wants to write a use case, document a user-system interaction in formal structure, produce use case specifications, or create a use case suite. Triggers on phrases like "viết use case", "use case spec", "viết theo dạng use case", "mô tả use case", "use case cho tính năng", "formal use case", "use case suite". Produces use cases with ID, name, level (user goal / subfunction / summary), primary actor, stakeholders, preconditions, main success scenario, extensions, exception flows, postconditions, special requirements. Stays at business interaction level — describes WHAT user and system do at business level, not technical implementation. Pairs with ba-user-story when formal use case is preferred over agile story format.
---

# BA Use Case (Cockburn-style)

Produce formal use cases that BAs use in enterprise or client-facing contexts where structured documentation is preferred over agile user stories. Output: Cockburn-style use case with named flows.

## The BA boundary for use cases

A use case describes **what the actor and system do** at business level — observable interactions, not technical implementation.

**Cover:** actor goals, preconditions, observable steps in interaction, decision points, alternative outcomes, postconditions, frequency, business-level non-functional requirements.

**Do NOT cover:** UI screens / wireframes, technical sequence (API calls, DB writes), code structure, specific error codes, technology choices.

**Acid test for a step:** "[Actor] does X" or "System does Y observably". If the step reads like "System calls service X with payload Y", that's drift to technical sequence — rewrite at business level ("System validates application").

## Use case vs user story (when to use this skill vs ba-user-story)

- **User story (ba-user-story):** agile, 1-2 paragraph + AC. Use when team is agile, story-based planning, dev pickup fast.
- **Use case (this skill):** formal, multi-section document with named flows. Use when stakeholder asks for "use case", enterprise / regulated context, client deliverable, complex interaction with many branches.

Don't dual-deliver. If unsure which the user wants, ask.

---

## Step 0 — Detect variant

| Variant | Signals | Output |
|---|---|---|
| **V-A — Standard use case** (default) | "viết use case cho [feature]", single interaction described | 1 fully-dressed use case |
| **V-B — Use case suite** | "use case suite", "viết các use case cho hệ thống X", multiple interactions implied | Multiple brief use cases + 1-2 fully-dressed key ones |

For V-B, default brief-level for most + ask which 1-2 to fully dress.

Record variant + rationale in metadata at top.

---

## Workflow

### Step 1 — Read context

Universal minimum:
1. What's the use case? (verb-object goal: "Đăng ký tài khoản", "Hoàn tiền cho khách hàng")
2. Who is the primary actor? (1 actor whose goal this satisfies)
3. What system is responding? (the business system, not a specific microservice)
4. What's the trigger?
5. What's the success outcome?

Variant-specific guidance: see `references/variants.md`.

If 2+ of these are vague, ask. Max 3 questions, batched. Track every assumption for appendix.

**Critical prerequisites.** If use case execution depends on external system or regulatory step whose availability/applicability is uncertain, flag at top.

### Step 2 — Set the use case level

Cockburn defines 3 levels (use ☁ / 🪁 / 🐟 as mental icons):

- **Summary (☁ kite-altitude)** — multiple user-goal use cases together, "manage account lifecycle"
- **User goal (🪁 sea-level)** — what user does in one sitting, ~5-15 min, achieves a complete business value: "Đăng ký tài khoản"
- **Subfunction (🐟 fish-level)** — a piece of a user goal, "Verify phone OTP"

**Most use cases should be at user-goal level.** Summary use cases are usually too vague; subfunction use cases are usually too granular (use steps inside a user-goal use case instead).

State the level explicitly in the use case header. If level doesn't match scope, refactor before writing.

### Step 3 — Determine sections

**Required for every use case (V-A standard or V-B fully-dressed):**
1. ID + Name + Level
2. Scope (which system)
3. Primary actor + secondary actors
4. Stakeholders and interests (who cares about this use case beyond the primary actor)
5. Preconditions
6. Trigger
7. Main Success Scenario (numbered steps)
8. Extensions (alternative flows, labeled by step + branch)
9. Special requirements (business-level NFRs for this use case)
10. Frequency / concurrency (business volume)
11. Postconditions — minimal guarantee (true even if scenario fails) + success guarantee
12. Open issues
13. Assumptions appendix

**Brief use case format (V-B brief level):** ID + Name + Level + Primary actor + Trigger + 1-paragraph narrative of main scenario + 1-2 key extensions + Postcondition. ~5-10 lines per use case.

### Step 4 — Write the Main Success Scenario

Number steps 1, 2, 3... Each step is one observable action by one actor or by the system. Format: **"[Actor] [verb-object]"** or **"System [verb-object]"**.

Target: 5-15 steps for a user-goal use case. If fewer, may be too coarse. If more, consider breaking into a summary use case + multiple user-goal use cases.

Rules:
- Each step is 1 action. Don't compose ("User submits form and system validates and shows result" → 3 steps)
- Each step has a clear actor (user, system, or named secondary actor)
- No technical drift — "User clicks Submit button" is OK; "User triggers POST /submit" is drift
- Steps must form a complete success path — actor's goal is achieved by the end

Example (Vietnamese):
```
1. Khách hàng nhập số điện thoại
2. Hệ thống kiểm tra số điện thoại chưa được đăng ký
3. Hệ thống gửi mã OTP qua SMS
4. Khách hàng nhập mã OTP
5. Hệ thống xác thực mã OTP
6. Khách hàng nhập thông tin cá nhân
7. Hệ thống lưu thông tin và kích hoạt tài khoản
8. Hệ thống hiển thị màn hình chào mừng kèm hướng dẫn các bước tiếp theo
```

### Step 5 — Write Extensions (alternative + exception flows)

Extensions are branches from main scenario steps. Label format: `[step number][branch letter]`.

Examples:
- `2a — Số điện thoại đã được đăng ký`: 1. Hệ thống thông báo "số đã đăng ký". 2. Hệ thống hiển thị CTA "Đăng nhập" hoặc "Quên mật khẩu". 3. Use case kết thúc.
- `3a — Không gửi được OTP (SMS provider lỗi)`: 1. Hệ thống ghi log lỗi. 2. Hệ thống hiển thị thông báo và CTA "Thử lại". 3. Quay lại bước 3 trong main scenario.
- `5a — OTP sai`: 1. Hệ thống tăng counter lỗi. 2. Nếu < 3 lần: yêu cầu nhập lại (về bước 4). 3. Nếu ≥ 3 lần: khóa OTP 15 phút, use case kết thúc với failure.

**Every extension must:**
- Be reachable from a specific step
- End at: (a) terminal state (success/failure), or (b) return to a specific step in main scenario, or (c) jump to another extension
- Not leave dangling — no "and then..." without resolution

Cover at minimum: every step that involves system validation, every step that involves external dependency, every step with multiple business outcomes.

### Step 6 — Write Postconditions

- **Minimal guarantee:** what's true regardless of whether scenario succeeds. Example: "Audit log records the attempt. No partial account is created."
- **Success guarantee:** what's true only if main scenario completes. Example: "User has an active account, can log in, sees the welcome screen."

Minimal guarantee is often forgotten but is critical — it tells you what *cannot* be in an inconsistent state.

### Step 7 — Surface what's likely missing

Common omissions for use cases:
- **Stakeholders beyond primary actor** — who else cares? (Audit, fraud, support, finance...)
- **Audit / compliance** — is this action logged? Is consent captured?
- **Concurrency** — what if same user runs this use case twice in parallel?
- **Idle / timeout** — what if user pauses mid-scenario? Session expiry?
- **External system unavailability** — explicit extension for each external dependency
- **Permission / role-based variations** — does behavior differ by user tier?

Variant-specific in `references/variants.md`.

### Step 8 — Self-check (rigor)

**Structural rigor:**
- Use case level stated and matches scope (☁ / 🪁 / 🐟)
- Main scenario has 5-15 steps for user-goal level
- Every step is one action by one actor
- Primary actor's goal is achieved by end of main scenario (if not → use case is misnamed or main scenario is incomplete)
- Every extension is reachable (referenced from a specific step)
- Every extension terminates (success, failure, or returns to main scenario)
- Every step that could fail has at least one extension
- Postconditions include both minimal and success guarantees

**Content rigor:**
- Stakeholders listed with their interest (not just names — what do they care about?)
- Preconditions are testable (can be checked before the use case starts)
- Special requirements are business-level NFRs (frequency, response time perception, audit)
- No technical drift in steps (no "API call", "DB write", "queue message")
- No UI specificity unless it carries business meaning (button names OK as shorthand; pixel/color is drift)

**Boundary check:**
- Steps describe WHAT, not HOW (no implementation hints)
- System is one logical entity, not multiple microservices
- External systems mentioned only by business name (e.g., "payment provider"), not by specific vendor unless the prompt named them

### Step 9 — Output

Default: Markdown in chat. Use case format is well-suited to text.

If user requests file ("xuất ra Word"), read `/mnt/skills/public/docx/SKILL.md`, produce .docx with: H1 = use case suite title, H2 = each use case, H3 = sections within use case.

**Canonical output order per use case:**
1. ID + Name + Level + Status (Draft default)
2. Metadata table (Variant, Variant rationale, Scope, Primary actor, Status, Date)
3. Critical prerequisites (if any) — flagged
4. Stakeholders and interests
5. Preconditions
6. Trigger
7. Main Success Scenario
8. Extensions (grouped by step)
9. Special requirements
10. Frequency / concurrency
11. Postconditions
12. Open issues
13. Assumptions appendix

For V-B suite: at the top, an index table of all use cases (ID, Name, Level, Primary actor, Status). Then each use case (brief or fully-dressed).

### Step 10 — Assumptions appendix (REQUIRED)

Same pattern. Group:
1. **Critical prerequisites** (flagged)
2. **Scope** — variant choice, use case level, what's in/out
3. **Actors** — who is primary, who is secondary (some prompts are ambiguous)
4. **Behavioral defaults** — defaults chosen when multiple were sensible (OTP attempts limit, timeout values, etc.)
5. **Coverage choices** — which extensions written, which omitted as "not in scope"

---

## Compose with other BA skills

After a use case is produced, offer:
- **Test cases from main scenario + extensions** → `ba-test-case` skill
- **User stories at agile granularity** (if team needs both formats) → `ba-user-story` skill
- **Process model of broader workflow this use case is part of** → `ba-process-model`
- **Detailed entity model for data referenced** → `ba-data-spec`

Don't auto-trigger. Offer at end.

---

## Language handling

Vietnamese canonical:

| English | Vietnamese |
|---|---|
| Use case | Trường hợp sử dụng / Use case |
| Primary actor | Tác nhân chính |
| Stakeholders and interests | Bên liên quan và mối quan tâm |
| Preconditions | Điều kiện tiên quyết |
| Trigger | Sự kiện kích hoạt |
| Main Success Scenario | Kịch bản thành công chính |
| Extensions | Các luồng mở rộng |
| Postconditions | Điều kiện sau khi thực thi |
| Special requirements | Yêu cầu đặc biệt |

Most VN BAs use "Use case" as a loanword; preserve as-is.

---

## Common anti-patterns

- **Step is more than one action** — split it
- **Step lacks an actor** — every step starts with [Actor] or System
- **Extension without resolution** — every extension ends with terminal state or return-to-step
- **Main scenario doesn't achieve goal** — check use case name matches the achievement
- **Technical drift in steps** ("System calls verification API") — rewrite at business level
- **Forgotten minimal guarantee** — postconditions only describe success; also state what's safe-on-failure
- **Use case at wrong level** — subfunction use cases are usually better as steps within a user-goal use case
- **UI-specificity drift** — "Click red Submit button at top right" is UX detail, not business action

## See also

- `references/variants.md` — V-A standard vs V-B suite: extra sections, questions, pitfalls
- `references/examples.md` — annotated good/bad use case excerpts + a complete short use case example
