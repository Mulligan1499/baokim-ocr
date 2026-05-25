---
name: ba-router
description: Route BA requests to the right skill(s) in the BA skill suite. Use when the user describes a BA need without naming a specific skill, when the user is unsure which skill fits, or when the request might compose multiple skills. Triggers on phrases like "tôi cần", "giúp tôi", "tôi đang", "skill nào", "which skill", "không biết dùng skill nào", "I need to", "tôi muốn", or any BA request that doesn't explicitly name a skill. Analyzes the user's description, matches against the 8 BA skills (ba-user-story, ba-feature-spec, ba-visualize, ba-use-case, ba-data-spec, ba-test-case, ba-stakeholder-meeting, ba-change-reporting) and their variants, recommends one primary skill with rationale, lists alternative skills if applicable, and suggests a compose chain when the workflow naturally extends across multiple skills. Confirms with the user before delegating execution.
---

# BA Router

Route user requests to the right skill in the BA suite. The user describes a need in their own words; the router identifies which skill (and variant) fits, surfaces alternatives, and proposes compose chains when the workflow extends across multiple skills.

This skill **does not produce BA deliverables itself** — it identifies which skill should produce them and confirms with the user before delegating.

---

## When this skill triggers

- User describes a BA need without naming a specific skill ("Tôi cần viết tài liệu cho client", "Giúp tôi document quy trình mới")
- User explicitly asks for help choosing ("Skill nào phù hợp?", "Which skill should I use?")
- User says "I don't know" or expresses uncertainty about toolchain
- User describes a multi-step BA workflow that may need compose chain

If user explicitly names a skill ("dùng `ba-feature-spec`", "chạy use case skill"), **don't route** — let the named skill take over.

---

## Step 0 — Parse the user's prompt

Identify signals from the prompt across 4 dimensions:

### 1. Deliverable type signals

What artifact does the user want to produce?

| Signal phrases | Likely skill |
|---|---|
| "user story", "story", "story for sprint", "agile story", "viết story" | Skill 1 (ba-user-story) |
| "FRD", "feature spec", "specification", "tài liệu chức năng", "spec cho feature" | Skill 2 V-A (ba-feature-spec internal) |
| "API spec", "API contract", "endpoint", "partner API", "tích hợp với partner X qua API" | Skill 2 V-B (API contract) |
| "integration spec", "system integration", "tích hợp 2 hệ thống", "đồng bộ giữa", "sync giữa" | Skill 2 V-C (multi-system) |
| "BRD", "business case", "ROI", "scope document", "tài liệu nghiệp vụ tổng quan" | Skill 2 V-D (BRD) |
| "use case", "Cockburn", "main scenario", "extensions", "primary actor" | Skill 4 (ba-use-case) |
| "quy trình", "process", "flow", "workflow", "AS-IS", "TO-BE", "BPMN" | Skill 3 V-A/B/C (process) |
| "user journey", "customer journey", "trải nghiệm", "touchpoint", "hành trình khách hàng" | Skill 3 V-D (journey) |
| "UI flow", "screen flow", "navigation", "luồng màn hình", "screens" | Skill 3 V-E (UI flow) |
| "state diagram", "lifecycle", "trạng thái", "states", "vòng đời" | Skill 3 V-G (state) |
| "data model", "ERD", "thực thể", "entity", "mô hình dữ liệu" | Skill 5 V-A (ERD) |
| "data dictionary", "từ điển dữ liệu", "catalog data" | Skill 5 V-B (dictionary) |
| "data mapping", "ánh xạ dữ liệu", "mapping fields giữa" | Skill 5 V-C (mapping) |
| "test case", "test scenarios", "kịch bản kiểm thử", "tests từ AC" | Skill 6 V-A (functional) |
| "UAT", "user acceptance test", "kịch bản UAT" | Skill 6 V-B (UAT) |
| "negative test", "edge case", "test trường hợp ngoài luồng" | Skill 6 V-C (negative) |
| "stakeholder", "ai liên quan", "bên liên quan", "power interest" | Skill 7 V-A (stakeholder analysis) |
| "RACI", "ai làm gì", "responsibility matrix", "phân công" | Skill 7 V-B (RACI) |
| "biên bản họp", "meeting minutes", "ghi nhận cuộc họp", "decision log" | Skill 7 V-C (minutes) |
| "communication plan", "kế hoạch giao tiếp", "cadence họp" | Skill 7 V-D (comm plan) |
| "change request", "CR", "yêu cầu thay đổi", "đề xuất extend" | Skill 8 V-A (CR) |
| "status report", "RAG", "báo cáo tiến độ", "weekly status" | Skill 8 V-B (status) |
| "gap analysis", "phân tích khoảng cách", "current vs target", "assessment" | Skill 8 V-C (gap) |
| "SWOT", "phân tích SWOT", "Strengths Weaknesses" | Skill 8 V-D (SWOT) |

### 2. Context signals (refine variant choice)

- **Agile context** ("sprint", "backlog", "story points") → push toward Skill 1, not Skill 4
- **Formal/regulated context** ("compliance", "audit", "client deliverable", "tài liệu submit") → push toward Skill 4, not Skill 1
- **Cross-system mention** (2+ systems named) → Skill 2 V-C, Skill 5 V-C
- **External party** ("partner", "third-party", "vendor") → Skill 2 V-B or V-C
- **High-volume scale** ("200 stores", "5 banks", "1M users") → trigger phasing in spec/process skills
- **Strategic horizon** ("Q3", "12 tháng tới", "annual") → Skill 8 V-D SWOT or V-C gap

### 3. Audience signals

- Sponsor/leadership/board → Skill 8 (especially V-B status, V-D SWOT)
- Dev team / sprint planning → Skill 1, Skill 2 V-A
- Client/external → Skill 2 V-D BRD, Skill 4
- QA team → Skill 6
- Compliance/audit → Skill 4 (formal), Skill 5 V-B (data dictionary with sensitivity)

### 4. Workflow stage signals

- **Pre-project** ("đang xem xét", "evaluating", "scoping") → Skill 8 V-D SWOT, Skill 2 V-D BRD
- **Project kickoff** ("vừa khởi động", "starting") → Skill 7 V-A stakeholders, V-B RACI, V-D comm plan
- **Mid-execution** ("đang chạy sprint", "in progress") → Skill 1 stories, Skill 8 V-B status
- **Pre-launch** ("chuẩn bị go-live", "before launch") → Skill 6 UAT, Skill 7 V-C training comm
- **Post-launch** ("sau khi launch", "post-go-live") → Skill 8 V-A change requests, V-B status

---

## Step 1 — Match against skills (ranking)

For each candidate skill, score:

1. **Direct trigger match** — does prompt have phrases from the deliverable type table? (Highest signal)
2. **Context fit** — does context match what the skill expects?
3. **Audience fit** — output audience match?
4. **Workflow stage fit** — appropriate stage?

Output ranking: Primary (highest score) + 1-2 alternatives + 1-3 compose-next suggestions.

---

## Step 2 — Detect compose chains

Some prompts naturally extend across multiple skills. Surface these proactively.

### Common compose patterns

**Pattern A — Feature from idea to launch:**
> Stakeholder analysis → BRD → FRD → Process model → User story / Use case → Test case → Comm plan → Status report

**Pattern B — Transformation project:**
> AS-IS process → Gap analysis → TO-BE process → Change request per recommendation

**Pattern C — Integration project:**
> API contract spec → Data mapping → Integration test cases → Status report

**Pattern D — Strategic review:**
> SWOT → Gap analysis → Change requests for top moves

**Pattern E — Project kickoff:**
> Stakeholder analysis → RACI → Comm plan → Initial status report cadence

When prompt signals a complex initiative (multi-stakeholder + multi-system + multi-phase), suggest the chain, not just one skill.

---

## Step 3 — Confirm before delegating

Present recommendation as a structured response. Do NOT auto-execute the primary skill — let user confirm or override.

### Output template

```markdown
## Skill recommendation

Dựa vào yêu cầu của bạn, đây là skill phù hợp:

**Primary:** `<skill-name>` <Variant code if applicable>
*Rationale:* <1-2 sentences why this fits>

**Có thể dùng kèm / thay thế:**
- `<alt-skill-1>` — nếu bạn muốn <Y>
- `<alt-skill-2>` — nếu bạn muốn <Z>

**Compose chain đề xuất** (workflow tự nhiên):
1. <step 1 skill> — output A
2. <step 2 skill> — output B (uses A as input)
3. <step 3 skill> — output C

Bạn muốn:
(a) Chạy primary ngay với prompt hiện tại
(b) Refine prompt và chạy primary
(c) Chạy compose chain bắt đầu từ bước 1
(d) Chọn skill khác (specify)
```

If user replies (a) or (c) → delegate to skill 1 of choice with the original prompt + context.

If user replies (b) → ask 1-2 clarifying questions to sharpen prompt, then delegate.

If user replies (d) → switch to user's choice.

---

## Step 4 — Handle ambiguity

If prompt is too vague to rank confidently (multiple skills tied, or no clear signal), ask **one** clarifying question with concrete options:

```
Yêu cầu của bạn có thể fit nhiều skill. Bạn muốn cái nào?

(a) Document chi tiết một tính năng cho dev impl → ba-feature-spec
(b) Document interaction formal cho audit/client → ba-use-case
(c) Story cho sprint planning → ba-user-story
(d) Cái khác (specify)
```

**Don't ask more than one question.** If user can't disambiguate after one question, default to most common interpretation and proceed — note the assumption.

---

## Examples

### Example 1 — Clear primary, suggests compose

**User:** "Tôi sắp triển khai tích hợp ví điện tử với 5 ngân hàng để làm QR Pay liên ngân hàng. Cần document cho team."

**Router output:**

> **Primary:** `ba-feature-spec` Variant C — Multi-system integration
> *Rationale:* "Tích hợp với 5 ngân hàng" signals multi-system integration spec, không phải single feature hay API contract đơn thuần. Variant C có sections phù hợp: sync mechanism, conflict resolution, fallback.
>
> **Alternative considerations:**
> - `ba-feature-spec` V-D (BRD) — nếu document cần ở level cao hơn (business case, ROI), viết BRD trước FRD
> - `ba-feature-spec` V-B (API contract) — nếu thực ra mỗi bank cần 1 API contract riêng (treat 5 contracts độc lập)
>
> **Compose chain đề xuất:**
> 1. `ba-feature-spec` V-D — BRD level cao trước
> 2. `ba-feature-spec` V-C — FRD chi tiết integration
> 3. `ba-data-spec` V-C — Data mapping cho từng bank
> 4. `ba-visualize` V-A — Process flow integration end-to-end
> 5. `ba-stakeholder-meeting` V-A — Stakeholder analysis (5 banks + internal teams)
> 6. `ba-test-case` V-A — Test cases từ AC
>
> Bạn muốn: (a) chạy primary, (b) bắt đầu compose chain từ BRD, (c) chọn khác?

---

### Example 2 — Ambiguous, ask clarifying

**User:** "Giúp tôi viết requirement cho tính năng quên mật khẩu."

**Router output:**

> Yêu cầu của bạn fit nhiều skill tùy methodology và level. Bạn muốn:
>
> (a) User story cho sprint planning (agile, ngắn gọn) → `ba-user-story`
> (b) Functional spec chi tiết (FRD với BR + AC chi tiết) → `ba-feature-spec` V-A
> (c) Use case formal Cockburn-style (cho client/audit) → `ba-use-case`
>
> Default nếu không trả lời: (a) — user story cho agile context. Bạn chọn?

---

### Example 3 — Workflow stage signal

**User:** "Project tích hợp POS-ERP vừa khởi động xong tuần trước. Mình là BA, mình nên làm gì trước?"

**Router output:**

> Project kickoff phase → typical BA priorities:
>
> **Primary chain** (project kickoff pattern):
> 1. `ba-stakeholder-meeting` V-A — Stakeholder analysis (identify ai liên quan, power/interest)
> 2. `ba-stakeholder-meeting` V-B — RACI matrix (ai làm gì trong project lifecycle)
> 3. `ba-stakeholder-meeting` V-D — Communication plan (cadence + channel)
> 4. `ba-feature-spec` V-D — BRD nếu chưa có (scope, business case)
> 5. `ba-visualize` V-A — AS-IS process mapping (hiểu hệ thống hiện tại)
>
> **Alternative starting points** (depending on project context):
> - Nếu scope chưa rõ → start với BRD trước (item 4)
> - Nếu team chưa rõ ràng → start với stakeholder analysis (item 1) — default
>
> Bạn muốn start với item nào?

---

### Example 4 — User has output from prior skill, looking for next

**User:** "Tôi đã viết user stories cho tính năng chuyển tiền (10 stories với AC). Tiếp theo nên làm gì?"

**Router output:**

> Bạn đã có stories + AC → compose pattern phổ biến nhất:
>
> **Primary next:** `ba-test-case` V-A — Test cases từ AC với coverage matrix
> *Rationale:* Skill 6 thiết kế để ăn AC từ ba-user-story làm input. Output sẽ có coverage matrix kiểm tra AC nào chưa được test.
>
> **Alternative next moves:**
> - `ba-feature-spec` V-A — Nếu cần FRD formal với business rules chi tiết (overlap với stories nhưng audience khác — sponsor/compliance)
> - `ba-visualize` V-A — Nếu cần process flow visualize cho discussion
> - `ba-data-spec` V-A — Nếu cần document data side (entities, relationships)
>
> Bạn muốn chạy ba-test-case ngay với AC bạn đã có?

---

## Edge cases

### Edge 1 — User wants help with something outside BA scope

Router detect requests that aren't BA work (e.g., "Code this for me", "Design UI mockup pixel-perfect", "Make architecture decision"):

> Yêu cầu của bạn có vẻ ngoài BA scope (e.g., technical implementation / UI design / architecture decision).
>
> Bộ skill BA cover: requirements (story/spec/use case), visualization (process/journey/state), data modeling (ERD/dictionary/mapping), test cases, stakeholder/meeting management, change/reporting/SWOT.
>
> Bạn có thể clarify nhu cầu BA-specific không? Hoặc reach out đến vai trò khác (dev, designer, architect) cho phần ngoài BA.

### Edge 2 — User describes multiple deliverables in one prompt

User: "Tôi cần story + use case + test case + process flow cho cùng feature."

Don't try to deliver 4 things in 1 skill call. Suggest sequence:

> Bạn cần 4 deliverables. Mình recommend sequence sau (each builds on prior):
> 1. `ba-feature-spec` V-A — FRD trước để align về scope + BR + AC chi tiết
> 2. `ba-user-story` — Stories từ FRD (lấy AC chi tiết, slice thành stories agile)
> 3. `ba-use-case` — Use case formal (parallel với stories, cùng feature, audience khác)
> 4. `ba-visualize` V-A — Process flow
> 5. `ba-test-case` V-A — Test cases từ AC
>
> Hoặc nếu bạn muốn parallel (không sequential): output mỗi skill độc lập, miễn có cùng context. Start với cái nào?

### Edge 3 — User pushes back, wants different skill

User: "Không, mình muốn dùng skill X (khác với recommendation)."

Respect user. Default to user's choice without arguing. Optionally note: "OK chạy skill X. Lưu ý skill X mạnh ở [Y], có thể không cover [Z] — nếu cần [Z], có thể chain thêm skill khác."

---

## Anti-patterns

- **Auto-execute skill mà không confirm** — luôn confirm trước
- **Recommend 5+ skills cùng lúc** — overwhelm user, max 3 primary + 1-3 compose
- **Hỏi nhiều clarifying questions** — max 1 question với 3-4 options, default if no answer
- **Argue khi user override** — user's call, respect
- **Recommend skill mà context không thực sự fit** — better to say "không có skill phù hợp 100%, đề xuất X làm best fit"
- **Quên compose chains** — surface workflow patterns proactively, không chỉ point skill

---

## Language handling

Match user's language for the routing response. Skill names (English identifiers) preserve as-is — như function names trong code. Vietnamese for rationale, options, instructions.

---

## See also

- `references/routing-patterns.md` — common workflow patterns + multi-skill chains for typical BA initiatives
