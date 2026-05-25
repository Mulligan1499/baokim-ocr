# Use Case Variant Recipes

## V-A — Standard use case (single, fully-dressed)

**Audience:** Dev + QA + stakeholders needing a complete formal description of one interaction.

**Required additional sections:** None beyond universal.

**Variant-specific questions if context is thin:**
1. What level (user goal / subfunction / summary)? Default to user goal.
2. Is the primary actor authenticated when this starts, or does authentication happen inside?
3. Any role-based variations (different user tiers having different flows)?

**Variant-specific pitfalls:**
- Writing one mega-use-case that should be 3 (sign of summary level when user-goal intended)
- Writing one micro-use-case that should be a step inside another (subfunction level when user-goal intended)
- Mixing UI specifics into business steps
- Forgetting role-based variations as extensions

**Typical length:** 1-2 pages per use case.

---

## V-B — Use case suite (multiple use cases for a feature/system)

**Audience:** Project teams needing a catalog covering a feature area.

**Required additional sections:**
- **Use case index** at the top: table of all use cases (ID, Name, Level, Primary actor, Status, Priority)
- **Actor catalog** before individual use cases — list all actors mentioned across the suite with their roles
- **Use case relationship diagram (optional)** — Mermaid showing «includes» and «extends» relationships if any

**Variant-specific questions:**
1. What's the scope of the suite? (one feature / one module / one system)
2. Brief level for all, or fully-dress some? (default: brief for all, ask which 1-2 to fully dress)
3. Is there a primary use case that orchestrates others? (often a "user goal" that includes subfunction use cases)

**Variant-specific pitfalls:**
- Over-decomposing — 30 use cases for a small feature means subfunction-level mistakes
- Under-decomposing — 1 mega-use-case for a whole product
- Skipping the actor catalog — readers lose track when 5+ actors appear across the suite
- Inconsistent naming (some "Đăng ký", some "Sign up", some "User registration") across same suite

**Typical length:**
- Brief use cases: 5-10 lines each, ~10-20 use cases per page
- 1-2 fully-dressed: 1-2 pages each
- Total: 5-15 pages for a typical feature

**When to use brief vs fully-dressed:**
- Brief: high-volume, low-risk, well-understood interactions ("View profile", "Logout")
- Fully-dressed: complex, high-risk, or stakeholder-debated interactions (core business operations)

Aim for ~80% brief / ~20% fully-dressed in a typical suite.

---

## Cockburn use case levels (refresher)

| Level | Icon | Scope | When to use |
|---|---|---|---|
| Summary | ☁ kite | Multi-session, multi-goal | When orchestrating multiple user goals (e.g., "Manage customer lifecycle") — rarely the right level for spec deliverable |
| **User goal** | 🪁 sea | One sitting, complete business value | **Default for most use cases** (e.g., "Mở tài khoản tiết kiệm") |
| Subfunction | 🐟 fish | A piece of a user goal | Use sparingly — usually better as a step inside a user-goal use case |

**Heuristic:** if user can finish the use case and walk away with the goal achieved, it's user-goal level. If they need to do more after to complete the bigger thing, it's subfunction.

---

## Common blind spots across variants

- **Audit / compliance footprint** — what gets logged, what consent is captured
- **Concurrent execution** — same user running the use case twice in parallel
- **Idle / session timeout** — what if user pauses mid-scenario
- **Cancellation** — can the user abort? At what steps?
- **External system unavailability** — explicit extension per external dependency
- **First-time vs returning** — onboarding states differ from repeat states
