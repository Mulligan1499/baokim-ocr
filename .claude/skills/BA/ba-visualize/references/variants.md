# Process Model Variant Recipes

Read the section matching your detected variant.

---

## V-A — Single process (AS-IS only OR TO-BE only)

**Audience:** Process owner, ops team, stakeholders who need to understand a single state.

### Required additional sections
None beyond universal.

### Variant-specific questions

**For AS-IS:**
1. Who owns this process today? (process owner authority is critical for confirming the model is right)
2. How long has the current process been running? (long-running processes accumulate workarounds you must capture)
3. Has anyone tried to map this before? (look for prior artifacts to reconcile, not re-invent)
4. What's the trigger to capture AS-IS now? (audit? transformation prep? new-hire onboarding? gap diagnosis?)

**For TO-BE:**
1. What change drives this redesign? (system replacement? regulatory mandate? scale stress? competitive pressure?)
2. Are there must-keep constraints? (specific actors that stay, regulatory steps that can't be removed, integrations that must continue)
3. Who will own the TO-BE process? (might be different from AS-IS owner — affects feasibility)
4. What's the transition timeline? (aggressive timeline → less aggressive redesign)

### Variant-specific pitfalls

**AS-IS:**
- Modeling what people **say** they do, not what they **actually** do
- Sanitizing — removing the ugly parts that are the most important data
- Missing unwritten escalation paths ("when X happens, Mary handles it personally — she's the only one who knows")
- Mapping multiple variants as if they were one (VIP vs standard customer paths blended)

**TO-BE:**
- Designing the diagram before deciding the improvement target
- Skipping trade-offs — making TO-BE look strictly better
- Ignoring change management cost (TO-BE may be technically simpler but require massive retraining)
- Pretending humans have unlimited capacity ("the manager will review every case" — at what volume?)

### Common blind spots
- **Empty / first-use** — for newly designed (TO-BE) processes, what about the very first run? Bootstrapping data, training fresh actors.
- **Process variants** — VIP customers, urgent cases, legacy data may flow differently. AS-IS often handles these implicitly.
- **After-hours / weekend / holiday** — does the process pause, escalate, queue, or have a different path?

### Typical length
1-3 diagrams + tables. 2-4 pages.

---

## V-B — AS-IS + TO-BE + gap analysis + recommendations

**Audience:** Sponsor, leadership, transformation committee, dev/ops teams who will build TO-BE.

**Tone:** decision-supporting. The reader is choosing whether/how to invest in this transformation.

### Required additional sections
- Gap analysis table (per AS-IS task: change type + pain addressed + risk introduced)
- Recommendations table (numbered, with effort + dependency + impact)

### Variant-specific questions
1. What's the business case for transformation? (cost reduction? speed? compliance? customer experience? competitive parity?)
2. Are improvement targets quantified? (e.g., "reduce processing time from 5 days to 1 day", "cut error rate from 5% to <1%")
3. Are there must-keep constraints? (specific actors, tools, regulatory steps that can't change)
4. Change tolerance? (Big-bang vs incremental? Risk appetite?)
5. Budget envelope? (affects whether to recommend tool adoption / new hires / org change)

### Variant-specific pitfalls
- TO-BE that ignores AS-IS pain — design from goal, but the goal should address actual pain
- Recommendation without pain trace — "adopt RPA" with no specific pain mapped
- Recommendation without effort estimate — leadership can't prioritize
- Phantom changes — TO-BE has tasks that don't map to AS-IS and aren't labeled **new**
- Missing risks — TO-BE always introduces new risk (training cost, transition fragility, automation failure modes); surface it explicitly
- Recommendation tied 1:1 with one pain — usually a good recommendation addresses multiple pains; over-narrow scoping inflates the count

### Common blind spots
- **Transition state** — between AS-IS shutdown and TO-BE go-live, what happens? Parallel run? Cutover? Phased?
- **Rollback plan** — if TO-BE fails after go-live, can we revert? What's lost in the revert?
- **Edge cases AS-IS handled implicitly** — tribal knowledge that TO-BE drops because it wasn't documented
- **Long-tail variants** — TO-BE optimizes the common case; what about the 5% edge cases? Are they handled, dropped, or escalated?
- **Cultural / political** — process change usually has human cost (someone's role becomes smaller); surface as a recommendation risk, not just operational

### Typical length
5-8 pages. Two diagrams + gap table + recommendations table + pain points.

---

## V-C — Sub-process zoom

**Audience:** Process owner of the specific area, dev/ops team for the specific sub-process.

### Required additional sections
- Parent process linkage (brief table)

### Variant-specific questions
1. Which parent process and which step are we zooming into? (need clear reference — parent diagram ID / step name)
2. What's the new granularity expected? (one level deeper, or fine-grained operational detail?)
3. Why zoom now? (implementation prep? bug investigation? optimization? training material?)

### Variant-specific pitfalls
- Drifting from the parent step's scope — the sub-process should refine, not redefine
- Adding actors not visible at parent level without flagging (creates orphans when reconciling)
- Losing connection points — sub-process input must match parent's output to it; sub-process output must match parent's expectation from it
- Re-modeling what's already covered elsewhere — check if the sub-process exists in another mapping first

### Common blind spots
- **Inputs from parent that weren't visible at parent level** — sub-process may consume context the parent diagram hid
- **Outputs back to parent** — does sub-process produce something parent expects?
- **Re-entry / iteration** — does the sub-process call back to itself or return to parent for retry?

### Typical length
1-2 pages, one diagram + small tables.

---

## When you need more than one variant

Some requests span variants:

- *"Vẽ quy trình hiện tại của X, và chi tiết hóa bước Y"* — V-A AS-IS + V-C zoom. Produce V-A first, then offer V-C for the specific step.
- *"Phân tích AS-IS vs TO-BE, và chi tiết hóa step quan trọng nhất trong TO-BE"* — V-B + V-C. V-B first, offer V-C zoom for the priority step after.

Don't blend variants in one diagram. Each variant has different rigor expectations.

---

## V-D — User journey map

**When this variant:** customer experience analysis, UX research findings, omnichannel touchpoint mapping, pain identification for product/service improvement.

**Audience:** Product team, UX team, Marketing, Customer Service, leadership reviewing CX.

### Required additional sections
- Persona definition (1-3 personas the journey is for)
- Phase breakdown (typically 4-6 phases: Awareness → Consideration → Onboarding → Active use → Renewal/Churn, but adapt to context)
- Touchpoint table (Phase × Touchpoint × Channel × Emotion × Pain × Opportunity)
- Opportunity table (consolidated improvements derived from touchpoints)

### Variant-specific questions
1. Is this current-state journey (existing customer) or target-state (designed for new product)?
2. What persona(s) does this journey describe?
3. Time horizon — one transaction, one onboarding, full customer lifecycle?
4. Emotion data — from user research, or BA inference based on observations?

### Variant-specific pitfalls
- Journey without emotions = just a process flow at higher level (defeats purpose)
- Single generic persona (defaults to "user") — be specific (e.g., "Mai, 28, first-time mobile banking user")
- Phases inconsistent (some at micro level "click button", some at macro "first month")
- Touchpoints listed without channel (in-app? email? phone? in-person? all channels visible matter)
- Opportunity column missing — surface improvements is the value-add

### Common blind spots
- Pre-awareness — how customer first learned about you
- Post-active — what happens after engagement (renewal, churn, recommendation)
- Negative paths — customer who didn't convert, churned, complained
- Multi-channel switching mid-journey (started mobile, finished in branch)

### Typical length
1 journey diagram + persona description + touchpoint table + opportunity table. 2-3 pages.

---

## V-E — UI flow / screen flow

**When this variant:** documenting screen-to-screen navigation, decision routing in UI, multi-screen workflows, onboarding flows.

**Audience:** UX designer (handoff for mockups), dev (for routing logic), QA (for navigation testing), product (for review).

### Required additional sections
- Screen table (Screen ID, Screen name, Entry from, Exit to, Purpose, Notes)
- Navigation rules (any conditional logic — "if X then go to Y")

### Variant-specific questions
1. What's the user starting context (logged in? first-time? specific entry point)?
2. Mobile, web, or both? (UI flow may differ)
3. Are there modal/overlay screens vs full-page screens to distinguish?
4. Conditional routing rules — when does flow branch?

### Variant-specific pitfalls
- Conflating UI flow with process flow (UI flow = screens; process = business activities)
- Including pixel-level UI design (use Figma instead)
- Orphan screens (in table but not in diagram) or unreachable screens (in diagram but no entry)
- Missing back/cancel paths — UI almost always has cancel; show or note explicitly

### Common blind spots
- Empty / error states per screen (often modeled as separate screens)
- Authentication / session timeout interrupts
- Deep linking (entering mid-flow from external link)
- Browser back button behavior (web only)

### Typical length
1 flow diagram + screen table. 1-3 pages depending on flow complexity.

---

## V-F — Screen description (no visual wireframe)

**When this variant:** documenting fields, actions, and validation per screen — for dev/designer handoff, or as part of FRD. Output is structured tables, NOT visual mockup.

**Audience:** UX designer (creates mockup from description), dev (implements logic), QA (validates fields).

### Required additional sections
- Per-screen tables (one table per screen):
  - Field table: Field name, Type (business: text, number, date, dropdown, file), Required, Validation rules, Default value, Notes
  - Actions table: Action name (button/link), Type (primary/secondary/destructive), Behavior (navigate to / submit / cancel), Conditions
- Cross-screen state assumptions (what's preserved across navigations)

### Variant-specific questions
1. Which screens to describe? (List or describe scope)
2. New screens, or documenting existing for handoff?
3. Validation rules — already defined elsewhere, or define here?
4. Localization — Vietnamese only, or multi-language?

### Variant-specific pitfalls
- Trying to do visual mockup in markdown/ASCII (looks unprofessional; recommend Figma)
- Field types in technical terms (VARCHAR(50)) instead of business (text, max 50 chars)
- Validation rules in code form (regex) instead of business form ("Vietnamese phone, 10 digits, starts with 0")
- Actions without "what happens after click" — every action needs behavior
- Forgetting empty/error states

### Common blind spots
- Disabled / loading states (button disabled when form invalid)
- Field interdependencies (field B becomes required when field A = X)
- Multi-step screens (when does intermediate save happen?)
- Accessibility considerations (label requirements, keyboard navigation hints)

### Output style note
This variant explicitly does NOT produce visual wireframes. Recommend at top of output:
> "For visual mockup, use Figma / Balsamiq / Whimsical. This deliverable is structured screen description suitable for dev/designer handoff."

### Typical length
1-2 pages per screen. Multi-screen flows: 5-15 pages.

---

## V-G — State diagram

**When this variant:** documenting entity lifecycle (Order: draft → submitted → approved → shipped → delivered), system states, workflow states.

**Audience:** Dev (state machine implementation), QA (state transition testing), product (lifecycle understanding).

### Required additional sections
- State table (State name, Description, Entry conditions, Exit conditions, Permitted actions in this state)
- Transition table (From state, To state, Trigger event, Guard conditions, Side effects in business terms)

### Variant-specific questions
1. What entity / object does this state machine describe?
2. Is initial state explicit (or system creates entity in default state)?
3. Are there terminal states (entity ends here, no further transitions)?
4. Concurrent state machines for same entity? (Most entities have 1; some complex ones have multiple — e.g., Order has status state + payment state independently)

### Variant-specific pitfalls
- Confusing state with status flag (state = exclusive, must be in exactly one; status flags = independent booleans)
- Missing transitions (state X has no exit — can entity get stuck there?)
- Triggers missing (transition labeled but doesn't say what triggers it)
- Side effects forgotten (state change should have business effect — log entry, notification, etc.)
- Multiple states reachable for same event (non-deterministic — usually a bug in design)

### Common blind spots
- Error / exception states (validation failure, system error mid-transition)
- Rollback / undo transitions (can entity move backward?)
- Cancellation transitions (most entities can be cancelled — from which states?)
- Auto-transitions (state changes from timer/scheduled event, not user action)
- Audit trail (each transition should record who/when/why)

### Typical length
1 state diagram + state table + transition table. 1-2 pages for simple lifecycle (3-5 states); 3-5 pages for complex (10+ states with concurrent machines).

---

## When variants compose

Common patterns:
- V-A AS-IS + V-D Journey: AS-IS process + customer journey for same flow → AS-IS shows actor activities, journey shows customer perspective. Together = complete picture.
- V-E UI flow + V-F Screen description: UI flow shows screen-to-screen, screen description shows what's on each screen. Pair naturally.
- V-G State diagram + V-A Process: process shows activities, state diagram shows entity lifecycle through process. Pair when entity is central.

Don't blend variants in one diagram. Each variant has its own diagrammatic conventions.
