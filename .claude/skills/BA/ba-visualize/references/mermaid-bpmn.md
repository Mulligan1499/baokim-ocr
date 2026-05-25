# Mermaid Templates for BPMN-flavored Process Modeling

Seven ready-to-adapt templates covering common BPMN patterns. Copy as starting point, then customize task names and add lanes.

---

## Template 1 — Linear flow with one decision

```mermaid
flowchart TD
  Start([User submits application]) --> T1[Verify identity]
  T1 --> T2[Check eligibility]
  T2 --> D1{Eligible?}
  D1 -->|Yes| T3[Approve and notify]
  D1 -->|No| T4[Reject and notify]
  T3 --> End1([Application approved])
  T4 --> End2([Application rejected])
```

**Use for:** straight-through processes with a single branch. Simplest pattern.

**Watch out for:** if you have only this, you may be sanitizing reality — most real processes have multiple decisions and exception paths.

---

## Template 2 — Multi-lane with handoffs

```mermaid
flowchart TD
  subgraph Customer
    Start([Customer initiates request]) --> T1[Submit form]
  end
  subgraph CSAgent [CS Agent]
    T1 --> T2[Review submission]
    T2 --> D1{Valid?}
    D1 -->|No, missing info| T3[Request clarification]
    T3 --> T1
  end
  subgraph BackOffice [Back Office]
    D1 -->|Yes| T4[Process request]
    T4 --> T5[Generate response]
  end
  subgraph CustomerReceive [Customer]
    T5 --> End([Customer receives response])
  end
```

**Use for:** any process spanning multiple actors with handoffs.

**Mermaid limitation:** swimlanes via subgraph are visually imperfect (the same actor appearing in two subgraphs renders as two groups). Accept this; it's still readable.

**Watch out for:** every cross-lane arrow is a handoff — explicit handoff steps (email, notification, system push) are often missing in casual mapping.

---

## Template 3 — Loop / iteration

```mermaid
flowchart TD
  Start([Reporting period begins]) --> T1[Generate report draft]
  T1 --> T2[Review draft]
  T2 --> D1{Approved?}
  D1 -->|Yes| T3[Publish report]
  D1 -->|No - needs revision| T1
  T3 --> End([Report published])
```

**Use for:** review-and-revise loops, retry patterns, periodic processes, approval cycles.

**Watch out for:** loops with no termination condition are bugs in the model — must have either a max-iteration limit or an alternative path out.

---

## Template 4 — Exception / unhappy path

```mermaid
flowchart TD
  Start([Order placed]) --> T1[Validate payment]
  T1 --> D1{Payment OK?}
  D1 -->|Yes| T2[Reserve inventory]
  D1 -->|No| E1[Notify payment failure]
  T2 --> D2{Inventory available?}
  D2 -->|Yes| T3[Confirm order]
  D2 -->|No| E2[Offer backorder or refund]
  T3 --> End1([Order confirmed])
  E1 --> EndF1([Order failed - payment])
  E2 --> EndF2([Order failed - inventory])
```

**Use for:** AS-IS with multiple failure modes, or TO-BE that explicitly handles exceptions.

**Watch out for:** every distinct failure outcome deserves its own end event — collapsing them into a generic "Failed" loses information.

---

## Template 5 — Parallel paths (AND-gateway)

```mermaid
flowchart TD
  Start([Loan application received]) --> Fork{Run parallel checks}
  Fork --> T1[Credit check]
  Fork --> T2[Document verification]
  Fork --> T3[Fraud screening]
  T1 --> Join{All complete?}
  T2 --> Join
  T3 --> Join
  Join -->|Yes| T4[Consolidate results]
  T4 --> D1{All passed?}
  D1 -->|Yes| End1([Approved])
  D1 -->|No| End2([Rejected])
```

**Use for:** activities that run in parallel and must all complete before the next step.

**Note:** Mermaid doesn't have native AND-gateway (parallel join) — approximate with a question gateway. Annotate clearly with a label.

---

## Template 6 — Sub-process (collapsed)

Parent diagram:

```mermaid
flowchart LR
  Start([Customer wants account]) --> T1[Receive request]
  T1 --> SP1[[KYC sub-process]]
  SP1 --> T2[Create account]
  T2 --> End([Account active])
```

Then zoom into the sub-process as a separate diagram (V-C variant):

```mermaid
flowchart TD
  StartSub([KYC starts: identity & address needed]) --> S1[Request ID document]
  S1 --> S2[Verify ID document]
  S2 --> D1{Valid?}
  D1 -->|No| S3[Request resubmission]
  S3 --> S1
  D1 -->|Yes| S4[Verify address]
  S4 --> D2{Address verified?}
  D2 -->|Yes| EndSub([KYC complete])
  D2 -->|No| S5[Escalate to manual review]
  S5 --> EndSub2([KYC complete with manual review])
```

**Use for:** breaking down a complex step at parent level, presenting at appropriate detail for audience.

**Watch out for:** sub-process input/output must match parent expectations exactly. If sub-process produces something parent doesn't expect, reconcile.

---

## Template 7 — AS-IS pattern showing pain

```mermaid
flowchart TD
  Start([Invoice received from vendor]) --> T1[CS receives invoice via email]
  T1 --> T2["CS prints invoice (paper)"]
  T2 --> T3["Walk paper to Finance desk"]
  T3 --> T4["Finance manually enters into ERP"]
  T4 --> D1{Data matches PO?}
  D1 -->|No, mismatch| T5[Email back to vendor for clarification]
  T5 --> T6[Wait 2-5 days for response]
  T6 --> T1
  D1 -->|Yes| T7[Finance approves payment]
  T7 --> End([Payment scheduled])
```

**Notes on what makes this AS-IS valuable:**
- Captures manual handoffs ("walk paper")
- Names the tool gaps (email, paper) — not sanitized to "submit document"
- Shows the loop on mismatch (typical pain point: long round-trips)
- Includes a wait task ("Wait 2-5 days") that's invisible work but real elapsed time

**Watch out for:** if your AS-IS looks like Template 1 (clean linear), you're sanitizing. Real AS-IS almost always has loops, manual steps, and exception paths.

---

## Naming & notation conventions (reference card)

| Element | Convention | Examples |
|---|---|---|
| Task | Verb + object | "Verify identity", "Approve refund", "Send notification" |
| Don't use | Noun-only, passive | ~~"Verification"~~, ~~"Refund is approved"~~ |
| Gateway | Short question or criterion | "Approved?", "Amount > 50M?", "In stock?" |
| Don't use | Long sentence, technical | ~~"Check if user.tier >= PREMIUM"~~ |
| Start event | Descriptive trigger | "Application received", "Period begins", "Customer requests refund" |
| Don't use | Bare "Start" | ~~"Start"~~, ~~"Begin"~~ |
| End event | Descriptive outcome | "Order confirmed", "Application rejected", "Payment scheduled" |
| Don't use | Bare "End" | ~~"End"~~, ~~"Done"~~ |
| Lane (subgraph) | Business role name | "CS Agent", "Finance Officer", "Approval Committee" |
| Don't use | Personal name | ~~"Mr. Nguyen"~~, ~~"Mary"~~ |
| Flow from gateway | Always labeled with condition outcome | `D1 -->|Yes\| T2`, `D1 -->|No, missing info\| T3` |
| Don't use | Bare arrow from gateway | ~~`D1 --> T2`~~ |

---

## Mermaid escaping gotchas

- **Parentheses in task names:** wrap in quotes — `T1["CS prints invoice (paper)"]` not `T1[CS prints invoice (paper)]`
- **Special characters** (colons, semicolons, brackets): wrap in quotes
- **Long task names:** Mermaid wraps automatically; use `<br>` for explicit line break: `T1["Verify identity<br>(KYC level 2)"]`
- **Multi-line gateway questions:** wrap with quotes and `<br>`

---

## When NOT to use Mermaid

Migrate to a real BPMN tool when:
- The diagram needs to be executable (Camunda, jBPM)
- Audit / regulatory body requires formal BPMN 2.0 notation
- The process has >20 tasks (Mermaid layout gets messy)
- You need rich annotation (call activities, event sub-processes, compensating tasks)
- Stakeholders are reviewing in a context where BPMN literacy matters

For 80% of BA day-to-day work, Mermaid is sufficient and faster.
