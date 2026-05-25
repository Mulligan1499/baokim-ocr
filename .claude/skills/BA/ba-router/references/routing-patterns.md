# Routing Patterns — Common BA Workflows

Multi-skill compose chains for typical BA initiatives. Use as templates when user describes a scenario matching one of these.

---

## Pattern A — New feature from idea to launch

**Trigger signals:** "tính năng mới", "feature mới", "khởi tạo project tính năng", "đang scope feature"

**Phases and skills:**

1. **Scoping** — `ba-stakeholder-meeting` V-A (stakeholder analysis) + `ba-feature-spec` V-D (BRD)
2. **Detail design** — `ba-feature-spec` V-A (FRD) + `ba-data-spec` V-A (ERD) + `ba-visualize` V-A (process)
3. **Granular deliverables** — `ba-user-story` (stories) HOẶC `ba-use-case` (formal)
4. **Testing prep** — `ba-test-case` V-A (functional) + `ba-test-case` V-B (UAT)
5. **Project comm** — `ba-stakeholder-meeting` V-B (RACI) + V-D (comm plan)
6. **Mid-project** — `ba-stakeholder-meeting` V-C (minutes) + `ba-change-reporting` V-B (status)
7. **Post-launch** — `ba-change-reporting` V-B (status) + V-A (CR for iterations)

**Variations:**
- Agile: skip V-A use case, focus stories
- Formal/regulated: include both stories + use case if methodology requires
- Single-team: skip multi-stakeholder analysis, simplify

---

## Pattern B — Transformation / improvement project

**Trigger signals:** "cải tiến quy trình", "transformation", "tối ưu hóa", "redesign", "AS-IS analysis"

**Phases:**

1. **Discovery** — `ba-visualize` V-A AS-IS + pain points + `ba-stakeholder-meeting` V-A
2. **Assessment** — `ba-change-reporting` V-C (gap analysis current vs target)
3. **Design** — `ba-visualize` V-B (AS-IS + TO-BE + gap + recommendations)
4. **Decision** — `ba-change-reporting` V-A (CR per major recommendation)
5. **Build phase** — switch to Pattern A workflow for each approved CR

**Variations:**
- Audit-driven: start with V-C gap analysis directly, AS-IS comes from audit findings
- Strategic: add `ba-change-reporting` V-D SWOT before V-C gap

---

## Pattern C — Integration project (multi-system)

**Trigger signals:** "tích hợp 2 hệ thống", "integration với", "API contract với partner", "đồng bộ giữa"

**Phases:**

1. **Scoping** — `ba-feature-spec` V-D (BRD with integration scope) + `ba-stakeholder-meeting` V-A (internal + external partners)
2. **Contract design** — `ba-feature-spec` V-B (API contract) OR V-C (multi-system) + `ba-data-spec` V-C (mapping)
3. **Process design** — `ba-visualize` V-A (integration flow end-to-end)
4. **Testing prep** — `ba-test-case` V-A + V-C (negative cases extra critical for integration)
5. **Coordination** — `ba-stakeholder-meeting` V-B (RACI extends to partner) + V-C (joint meeting minutes)

**Variations:**
- 1-way API consumption: V-B simpler
- 2-way sync: V-C with conflict resolution
- Multiple partners (like 5 banks): scope as 1 framework + per-partner adaptations

---

## Pattern D — Strategic review

**Trigger signals:** "annual review", "Q3 strategic", "đánh giá định kỳ", "kế hoạch chiến lược"

**Phases:**

1. **SWOT** — `ba-change-reporting` V-D (Strengths/Weaknesses/Opportunities/Threats with TOWS)
2. **Gap deep-dive** — `ba-change-reporting` V-C (for top strategic moves)
3. **Project initiation** — for each top move: switch to Pattern A or B
4. **Stakeholder alignment** — `ba-stakeholder-meeting` V-A + V-D (comm strategy)

---

## Pattern E — Project kickoff

**Trigger signals:** "project vừa khởi động", "starting new project", "tuần đầu project", "PM mới onboard"

**Phases (typically 1-2 weeks):**

1. **Stakeholder mapping** — `ba-stakeholder-meeting` V-A
2. **Responsibility clarification** — `ba-stakeholder-meeting` V-B (RACI)
3. **Comm cadence** — `ba-stakeholder-meeting` V-D (comm plan)
4. **Scope baseline** — `ba-feature-spec` V-D (BRD) if not existing
5. **Initial process baseline** — `ba-visualize` V-A AS-IS (if applicable)
6. **First status report cadence** — `ba-change-reporting` V-B

---

## Pattern F — Mid-project change handling

**Trigger signals:** "phát hiện vấn đề", "cần extend", "scope creep", "blocker xuất hiện"

**Steps:**

1. **Document the change need** — `ba-change-reporting` V-A (change request with impact analysis)
2. **Communicate** — `ba-stakeholder-meeting` V-C (decision meeting minutes if approved)
3. **Update artifacts** — re-run affected skills (spec / process / stories / etc.) for changed parts
4. **Track** — `ba-change-reporting` V-B (next status report references CR-XYZ approval)

---

## Pattern G — Pre-launch checklist

**Trigger signals:** "chuẩn bị go-live", "before launch", "UAT prep", "go-live readiness"

**Skills to invoke:**

1. **UAT preparation** — `ba-test-case` V-B (UAT scenarios for real business users)
2. **Communication ready** — `ba-stakeholder-meeting` V-D updated with launch comm
3. **Training material** — sometimes `ba-use-case` (formal use cases as training reference)
4. **Risk assessment** — `ba-change-reporting` V-B with elevated detail on launch risks
5. **Decision log** — `ba-stakeholder-meeting` V-C (go/no-go meeting minutes)

---

## Pattern H — Data-centric initiative

**Trigger signals:** "data governance", "data catalog", "compliance audit data", "data migration"

**Skills:**

1. **Existing inventory** — `ba-data-spec` V-B (data dictionary current state)
2. **Target model** — `ba-data-spec` V-A (conceptual ERD target state)
3. **Migration mapping** — `ba-data-spec` V-C (current → target mapping)
4. **Gap and changes** — `ba-change-reporting` V-C (data capability gap)
5. **Test plan** — `ba-test-case` V-A with data integrity focus

---

## When patterns combine

Real BA work often combines patterns. Examples:

- **Pattern B + C:** transformation that requires new integration → AS-IS analysis + integration design
- **Pattern A + D:** SWOT identifies strategic move → becomes new feature project
- **Pattern F appears mid Pattern A or B or C:** CRs happen during any execution phase

When user's request hints at multiple patterns, surface both — let user choose primary entry point.

---

## Router heuristics

| User signal | Likely pattern |
|---|---|
| "vừa khởi động" / "kickoff" | E |
| "scope creep" / "extend" / "change" | F |
| "annual" / "strategic" / "Q-XX" | D |
| "AS-IS" / "transformation" / "cải tiến" | B |
| "tích hợp" / "integration" / "API với" | C |
| "go-live" / "launch" / "UAT prep" | G |
| "data" + "migration/governance/catalog" | H |
| "tính năng mới" / "feature mới" | A |

When 2 patterns match equally, recommend the earlier-phase one. If user says "tích hợp tính năng mới với partner", that's Pattern A + C — start with A (broader scoping), include C steps within.
