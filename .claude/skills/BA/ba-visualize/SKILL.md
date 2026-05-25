---
name: ba-visualize
description: Produce BA-level visualizations — process flows (AS-IS / TO-BE / comparison), user journey maps, UI flows, state diagrams, and sub-process zooms. Use whenever the user wants to visualize, diagram, map, or draw something for BA work. Triggers on phrases like "vẽ quy trình", "process map", "AS-IS", "TO-BE", "BPMN", "flowchart", "sơ đồ", "user journey", "customer journey", "hành trình khách hàng", "UI flow", "screen flow", "luồng màn hình", "state diagram", "lifecycle", "vòng đời", "trạng thái". Produces Mermaid-based diagrams (BPMN-flavored, journey, flowchart, stateDiagram-v2) plus supporting tables (task descriptions, pain points, gap analysis, screen descriptions, state transitions). Stays at business level — WHAT happens, WHO acts, WHAT decisions branch — without drifting into technical implementation (API sequences, code, infrastructure, pixel-perfect UI design).
---

# BA Visualize

Produce BA-level visualizations for the most common visual deliverables BAs need: process flows, user journeys, UI flows, and state diagrams. Output is Mermaid-based (renders inline in chat) plus supporting tables. Stays at business activity level — what happens, who acts, what decisions branch — without drifting into technical implementation.

## The BA boundary for visualizations

A BA visualization **shows**:
- **Business activities, decisions, events, artifacts** — at business level meaningful to non-technical readers
- **Business actors** — roles, departments, systems acting at business level
- **Business state / experience** — touchpoints, channels, emotions (for journey), screens (for UI flow), states (for lifecycle)
- **Business timing** — durations, SLAs, frequencies from business perception

A BA visualization **does NOT show**:
- Technical sequence diagrams of system internals (API calls between services)
- Database operations, locks, transactions
- Pixel-perfect UI design (use Figma / Balsamiq / Whimsical for that)
- Code organization or class diagrams
- Infrastructure components (queues, load balancers, microservices)
- Detailed visual design (colors, fonts, exact layout)

**The line:** if a non-technical reader can read your diagram and understand what's happening from a business POV, you're at the right level. If they need technical knowledge to follow, you've drifted.

For UI work specifically: this skill produces **UI flow** (screen-to-screen navigation) and **screen description** (fields, actions per screen) — NOT visual mockups. For visual mockups, recommend the user use Figma / Balsamiq / Whimsical.

---

## Note on Mermaid limits

This skill produces **Mermaid-based diagrams**, not pure BPMN 2.0 / formal UML. Trade-off:

- Renders in chat immediately, iterates fast, embeds in any markdown wiki
- Sufficient for collaborative discussion, requirements gathering, stakeholder review
- Not formal notation — no proper BPMN/UML symbols; swimlanes approximated via subgraph
- For formal deliverable (BPMN audit, UML compliance, regulatory submission), migrate to a real tool (draw.io, Lucidchart, Camunda Modeler for BPMN; specialized tools for UML)

Always communicate this trade-off at the end of the output. Don't pretend Mermaid is formal notation.

---

## Step 0 — Detect variant

| Variant | Signals | Output type |
|---|---|---|
| **V-A — Single process** (default for "vẽ quy trình") | "vẽ quy trình", "process map", "AS-IS only", "TO-BE only" | Mermaid flowchart + task table + pain points (if AS-IS) |
| **V-B — AS-IS + TO-BE + gap** | "phân tích AS-IS vs TO-BE", "cải tiến quy trình", "transformation", "trước và sau" | 2 Mermaid flowcharts + gap table + recommendations |
| **V-C — Sub-process zoom** | "chi tiết hóa step Y", "deep-dive vào bước", "expand task X" | 1 zoomed flowchart + task detail + parent linkage |
| **V-D — User journey** | "user journey", "customer journey", "hành trình khách hàng", "trải nghiệm khách hàng", "touchpoint" | Mermaid journey diagram + emotion track + opportunity table |
| **V-E — UI flow / screen flow** | "UI flow", "screen flow", "navigation", "luồng màn hình", "screen-to-screen" | Mermaid flowchart with screens as nodes + screen description table |
| **V-F — Screen description** | "mô tả màn hình", "screen spec", "fields trên màn hình X" | Screen description tables (NO visual wireframe — recommend tool ngoài) |
| **V-G — State diagram** | "state diagram", "vòng đời", "trạng thái của entity", "lifecycle", "state transitions" | Mermaid stateDiagram-v2 + state description table + transition rules |

If signals are ambiguous, ask 1 clarifying question with concrete options.

Record variant + rationale in the metadata table at top.

For deep per-variant guidance, read `references/variants.md`.

---

## Workflow (universal across variants)

### Step 1 — Read context

Universal minimum:
1. What is being visualized? (one-line scope)
2. Where does it start? (trigger / start event / first screen / initial state)
3. Where does it end? (outcome / final screen / terminal states)
4. Who are the main actors? (humans + systems for process/journey/UI; not applicable for state diagram)

Variant-specific questions in `references/variants.md`. Read for your variant before asking.

Ask the fewest questions needed (max 3, batched). Default and proceed if 1 item is fuzzy; track every assumption.

**Large-scale trigger.** If visualization spans >5 actors, >10 steps/screens, multiple departments, or multi-month timeline, surface phasing or scope-reduction ("End-to-end at high level, then drill into specific sub-areas?"). Don't model 30 steps / 30 screens at full detail in one diagram.

**Critical prerequisites.** If visualization depends on assumptions whose falsity would change the model fundamentally, flag at top.

### Step 2 — Determine sections

Universal sections (every variant):
- Visualization metadata (variant, variant rationale, scope, owner, status, date)
- Critical prerequisites (if any)
- Main diagram(s) (Mermaid)
- Supporting tables (variant-specific)
- Open questions / topics to confirm
- Note on Mermaid limits
- Assumptions appendix

Conditional sections (variant-specific):
- Pain points table — V-A AS-IS, V-B AS-IS
- Gap analysis + recommendations — V-B only
- Parent process linkage — V-C only
- Emotion track + opportunity table — V-D only
- Screen description tables — V-E, V-F
- State transition rules — V-G only
- Glossary — if ≥3 domain terms might confuse outside readers

### Step 3 — Variant-specific mental modeling

Read `references/variants.md` for your variant. Each variant has its own modeling approach:

- Process (V-A/B/C): see `references/mermaid-bpmn.md` for 7 BPMN-flavored templates
- Journey (V-D): focus on customer's perspective — phases, touchpoints, emotions, opportunities. See `references/mermaid-journey.md`
- UI flow (V-E): screens as nodes, navigation as edges, conditional routing as gateways. See `references/screen-description.md`
- Screen description (V-F): structured table of fields, actions, validation per screen. No visual wireframe — recommend tool. See `references/screen-description.md`
- State diagram (V-G): states + transitions + transition triggers. See `references/mermaid-state.md`

### Step 4 — Draw the Mermaid

Common rules across all variants:

- Naming consistent within diagram (verb-object for actions, noun for states/screens/touchpoints)
- Labels descriptive, not bare ("Application received", not "Start")
- No technical drift (no "API call", "DB query", "POST /endpoint" as labels)
- Match user's language (don't auto-translate Vietnamese to English in labels)

Variant-specific syntax in referenced template files.

### Step 5 — Document supporting tables

For each variant, the tables differ. Universal principle: every diagram node should have a corresponding row in a supporting table, and vice versa (no orphans either way).

- Process: Task descriptions table (ID, Task, Actor, Input, Output, Duration, Notes)
- Journey: Touchpoint table (Phase, Touchpoint, Channel, Emotion, Pain, Opportunity)
- UI flow: Screen table (Screen ID, Name, Entry from, Exit to, Purpose)
- Screen description: Field table per screen (Field, Type, Required, Validation, Note)
- State diagram: State table (State, Description, Entry conditions, Exit conditions) + Transition table (From state, To state, Trigger, Guard conditions)

### Step 6 — Variant-specific outputs

- V-A AS-IS / V-B AS-IS half: pain points table (required)
- V-B: gap analysis table + recommendations table
- V-C: parent linkage section
- V-D: opportunity table (improvements derived from pain/emotion analysis)
- V-F: explicit note: "For visual mockup, recommend Figma / Balsamiq / Whimsical. This skill produces structured description only."
- V-G: transition rules (what triggers each transition, guard conditions, side effects in business terms)

### Step 7 — Surface what's likely missing

Variant-specific blind spots in `references/variants.md`. Common across all visualizations:
- Exception / failure paths
- Empty / first-use states
- After-hours / weekend / holiday handling
- Concurrency (parallel actors / sessions)
- Audit / compliance requirements
- Process / journey / UI / state variations (VIP, urgent, legacy)

### Step 8 — Self-check (rigor)

Universal diagram rigor:
- Every node has descriptive label (no bare "Start", "Step 1", "Screen A")
- Every flow / transition labeled (no bare arrows from gateways or state machines)
- Every loop is explicit (back-arrows with labels)
- No technical drift (no API/DB/code references)
- Diagram terminates (no dangling nodes)

Universal content rigor:
- Supporting tables have every diagram node; diagram has every table row (no orphans)
- Numbers (durations, volumes) traceable to source or "TBD — [owner]"
- Critical prerequisites surfaced at top
- Glossary present if ≥3 domain terms

Variant-specific rigor:
- V-A AS-IS / V-B AS-IS: ≥2-3 pain points captured (zero = sanitized); ≥1 exception path shown
- V-B: every TO-BE node maps to AS-IS via change type OR marked "new"; every pain has ≥1 recommendation
- V-D: every touchpoint has channel + emotion + opportunity; phases ordered chronologically
- V-E: every screen has entry/exit; no orphan screens (screen reachable from start?)
- V-F: every field has required/optional + validation + note (even if "none" for "no validation")
- V-G: every state reachable (from initial state); every state can exit (or marked terminal); transitions have triggers (not just labels)

Boundary check:
- No technical sequence diagrams
- No pixel-perfect UI design
- No infrastructure components
- All actors / systems at business level

### Step 9 — Output

Default: Markdown in chat. Mermaid renders inline.

If user requests file: read `/mnt/skills/public/docx/SKILL.md` first; produce .docx with Mermaid as images if supported, else code blocks with note.

Canonical output order:
1. Title + 1-line scope
2. Metadata table (Variant, Variant rationale, Owner, Status, Date)
3. Critical prerequisites (if any)
4. Actors / personas / context (variant-specific)
5. Main diagram(s)
6. Supporting tables
7. Variant-specific outputs (pain points / gap / opportunity / screen description / state transitions)
8. Open questions
9. Note on Mermaid limits
10. Assumptions appendix

### Step 10 — Assumptions appendix (REQUIRED)

Same pattern. Group:
1. Critical prerequisites (flagged)
2. Scope — variant choice + rationale, what's in/out
3. Granularity — level of detail, what's collapsed
4. Modeling defaults — assumed durations, frequencies, percentages
5. Variant-specific interpretation:
   - AS-IS pain points (confirmed vs hypothesis)
   - TO-BE design choices (when multiple were sensible)
   - Journey emotions (BA's inference vs user research data)
   - UI flow assumptions (default screen ordering, navigation patterns)
   - State machine assumptions (default initial state, error states)

---

## Compose with other BA skills

- Process model (V-A/B/C) → feature specs / user stories: TO-BE recommendations often become features → ba-feature-spec or ba-user-story
- User journey (V-D) → feature specs: opportunities identified → features
- UI flow (V-E) → use cases / stories: each screen interaction often becomes a use case or story
- State diagram (V-G) → data spec: entity states often documented as enum attribute → ba-data-spec
- Any visualization → test cases: flows and states need test coverage → ba-test-case

---

## Language handling

Vietnamese canonical headers:

| English | Vietnamese |
|---|---|
| Process metadata | Thông tin chung |
| Critical prerequisites | Điều kiện tiên quyết |
| Actors / lanes | Tác nhân / vai trò |
| Process diagram | Sơ đồ quy trình |
| User journey | Hành trình người dùng |
| Touchpoint | Điểm chạm |
| Phase | Giai đoạn |
| Emotion | Cảm xúc |
| Opportunity | Cơ hội cải tiến |
| UI flow / Screen flow | Luồng màn hình |
| Screen description | Mô tả màn hình |
| State diagram | Sơ đồ trạng thái |
| State transition | Chuyển trạng thái |
| Task descriptions | Mô tả tác vụ |
| Pain points | Điểm đau / vấn đề hiện tại |
| Gap analysis | Phân tích chênh lệch |
| Recommendations | Đề xuất cải tiến |
| Open questions | Câu hỏi mở |
| Assumptions appendix | Phụ lục giả định cần xác nhận |

Inside diagrams, match user's language.

---

## Common anti-patterns

- Mega-diagrams (30+ nodes) — unreadable; break into parent + sub-diagrams
- No exception paths in AS-IS — sanitizing makes the model useless
- Naming inconsistency — mixing verb-object with noun-only within one diagram
- Unlabeled gateways / transitions — ambiguous
- Technical drift in labels — "POST /api/X" instead of "Submit application"
- TO-BE without trade-offs — implying TO-BE is strictly better
- Recommendation without traceability — no link to specific pain
- Phantom changes in gap analysis — TO-BE has nodes not mapping to AS-IS, not marked new
- Pretending to do visual UI design — recommend Figma / Balsamiq instead
- Personal names in lanes — use roles
- State diagram without triggers — transitions need to be triggered by something (event, condition, action)
- Journey without emotions — defeats the purpose; touchpoints alone is just a process

---

## See also

- `references/variants.md` — deep per-variant guidance for all 7 variants
- `references/mermaid-bpmn.md` — 7 Mermaid templates for process flows
- `references/mermaid-journey.md` — Mermaid journey diagram templates + emotion track
- `references/mermaid-state.md` — Mermaid stateDiagram-v2 templates + state machine patterns
- `references/screen-description.md` — UI flow + screen description format
- `references/examples.md` — annotated examples for all variants
