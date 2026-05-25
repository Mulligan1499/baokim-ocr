# Skill Ecosystem — Baokim OCR Project

> Repo skills cho **Cuộc thi Claude Skill 2026 — Baokim**. Tổng hợp skills từ 3 vai trò: Dev, QA/Tester, BA. Xây dựng theo **Anthropic Claude Skill standard** + **Baokim enterprise extensions**.

**Project context**: API OCR cho team KSNB (Kiểm soát nội bộ) Baokim — onboarding merchant. Nhận ảnh/PDF, trả structured JSON + bản dịch + confidence. Pipeline 7 stages với defense-in-depth chống AI hallucination.

→ Xem [project README](../../README.md) để biết về API và kiến trúc tổng thể.

---

## 📁 Folder Structure (Nested by role)

```
.claude/skills/
├── README.md                  # File này — overview + navigation
├── PROGRESSIVE_DISCLOSURE.md  # Pattern doc cho lazy-load detail
├── DEV/                       # 12 skill em build (Dev role)
│   ├── README.md              # Catalog 12 skill
│   ├── cross-llm-extraction-prompt/SKILL.md
│   ├── tech-decision-framework/SKILL.md
│   ├── vn-pii-masker/SKILL.md
│   ├── feedback-to-skill-refiner/SKILL.md
│   ├── dev-baokim-api-design/SKILL.md
│   ├── dev-baokim-db-schema-designer/SKILL.md
│   ├── dev-baokim-laravel-repo-pattern/SKILL.md
│   ├── dev-baokim-sql-reviewer/SKILL.md
│   ├── dev-vision-prompt-designer/SKILL.md
│   ├── ocr-extraction-validator/SKILL.md
│   ├── ocr-confidence-aggregator/SKILL.md
│   └── ocr-document-classifier/SKILL.md
├── QA/                        # Team QA đẩy skill vào đây
│   └── README.md              # Hướng dẫn + catalog
└── BA/                        # Team BA đẩy skill vào đây
    └── README.md              # Hướng dẫn + catalog
```

### ⚠️ Lưu ý quan trọng về Anthropic spec

Anthropic Claude Skill spec recommend **flat structure** `.claude/skills/<name>/SKILL.md`. Em chọn **nested structure** `.claude/skills/<role>/<name>/SKILL.md` để:
- Clean folder cho BGK review qua git
- Tách rõ ownership theo vai trò (Dev/QA/BA)
- Dễ scale khi nhiều team contribute

**Trade-off**: Claude Code auto-load chỉ scan flat path → skill nested **không auto-trigger** trong Claude Code local. Workaround khi cần activate:

```bash
# Tạm thời copy skill ra flat path
cp -r .claude/skills/DEV/dev-baokim-sql-reviewer .claude/skills/

# Sau khi dùng xong, xóa khỏi flat path
rm -rf .claude/skills/dev-baokim-sql-reviewer
```

→ Cho mục đích submission BGK (review markdown như documentation), nested structure không ảnh hưởng. Cho production deploy, có thể chuyển sang flat hoặc dùng symlink hybrid.

---

## Quick Stats

| Metric | Value |
|---|---|
| **Tổng skills** | 12 (Dev) + N (QA) + M (BA) — extensible |
| **Reusability rate** | 11/12 dev skills (92%) reusable ngoài project |
| **Anthropic spec compliance** | ✓ Hybrid: `description` + `when_to_use` + Baokim extensions |
| **Domain coverage** | OCR pipeline 7 stages + Baokim platform standards + cross-domain foundations |
| **Phase usage** | 7 build-time + 4 runtime-embedded + 1 feedback loop |

---

## Architecture — 3 vai trò × 4 reusability tiers

### Vai trò (role-based naming convention)

| Prefix | Vai trò | Mục đích | Số skill hiện tại |
|---|---|---|---|
| `dev-baokim-*` | Dev (Baokim platform) | Code Laravel/SQL/API theo BKM01-08 standards | 4 |
| `dev-vision-*`, `ocr-*` | Dev (OCR domain) | Stage 1/2/3/5 pipeline OCR | 4 |
| `cross-*`, `vn-*`, `tech-*`, `feedback-*` | Dev (cross-domain) | Foundation patterns + utility | 4 |
| `qa-*` | QA / Tester | Test case design, bug report, regression | _Sẽ thêm từ team Tester_ |
| `ba-*` | BA (Business Analyst) | User story, AC writing, requirement extraction | _Sẽ thêm từ team BA_ |

### Reusability tiers (4 mức theo phạm vi reuse)

```
🟢 TẦNG A — Cross-domain (mọi team, mọi industry)
   └─ Foundation skills + cross-cutting concerns

🔵 TẦNG B — Baokim platform (mọi dự án Laravel/API tại Baokim)
   └─ Encode BKM01-08 standards

🟡 TẦNG C — Pattern reusable (design generic, OCR là 1 ví dụ)
   └─ Multi-signal scoring, validation patterns

🟠 TẦNG D — Project-specific (chỉ OCR Baokim KSNB)
   └─ Taxonomy 15 doc type cụ thể
```

---

## Skill Catalog

### 🟢 Tầng A — Cross-domain (4 skills)

| # | Skill | Mô tả ngắn | Phase |
|---|---|---|---|
| 01 | [`cross-llm-extraction-prompt`](DEV/cross-llm-extraction-prompt/SKILL.md) | Foundation prompt LLM extraction chống bịa, 7 anti-hallucination patterns | Build |
| 02 | [`tech-decision-framework`](DEV/tech-decision-framework/SKILL.md) | Phân tích trade-off + sinh ADR khi phân vân giữa nhiều approach | Build |
| 03 | [`vn-pii-masker`](DEV/vn-pii-masker/SKILL.md) | Che PII Việt Nam (CCCD/MST/phone/email) — giữ 4 ký tự cuối | Runtime (Stage 4) |
| 04 | [`feedback-to-skill-refiner`](DEV/feedback-to-skill-refiner/SKILL.md) | Phân tích thao tác user → đề xuất sửa skill khác. Vòng lặp tự cải thiện | Post-deploy (weekly) |

### 🔵 Tầng B — Baokim Platform (4 skills)

| # | Skill | Mô tả ngắn | Phase |
|---|---|---|---|
| 05 | [`dev-baokim-api-design`](DEV/dev-baokim-api-design/SKILL.md) | Thiết kế REST API theo 8 chuẩn Baokim (versioning/error R8/idempotency...) | Build |
| 06 | [`dev-baokim-db-schema-designer`](DEV/dev-baokim-db-schema-designer/SKILL.md) | Sinh migration theo BKM/SCR/IDR, tránh 8 trap Laravel default | Build |
| 07 | [`dev-baokim-laravel-repo-pattern`](DEV/dev-baokim-laravel-repo-pattern/SKILL.md) | Sinh code Repository/Service/Controller 3-layer theo BKM03 | Build |
| 08 | [`dev-baokim-sql-reviewer`](DEV/dev-baokim-sql-reviewer/SKILL.md) | Review SQL/Eloquent code theo SQR/IDR/BKM | Build |

### 🟡 Tầng C — Pattern Reusable (3 skills)

| # | Skill | Mô tả ngắn | Phase |
|---|---|---|---|
| 09 | [`dev-vision-prompt-designer`](DEV/dev-vision-prompt-designer/SKILL.md) | Thiết kế prompt Claude Vision API với 7 anti-hallucination patterns | Build (Stage 2 prompt) |
| 10 | [`ocr-extraction-validator`](DEV/ocr-extraction-validator/SKILL.md) | Validate output Stage 2 độc lập (rule regex + LLM judge KHÔNG xem ảnh) | Runtime (Stage 3) |
| 11 | [`ocr-confidence-aggregator`](DEV/ocr-confidence-aggregator/SKILL.md) | Tổng hợp multi-signal scoring (self + rule + judge) → quality bucket | Runtime (Stage 5) |

### 🟠 Tầng D — Project-Specific (1 skill)

| # | Skill | Mô tả ngắn | Phase |
|---|---|---|---|
| 12 | [`ocr-document-classifier`](DEV/ocr-document-classifier/SKILL.md) | Phân loại 15 doc type KSNB + ngôn ngữ + extraction strategy | Runtime (Stage 1) |

### 🧪 QA / Tester skills (chờ team QA add)

_Khi team QA gửi skills, sẽ list ở đây. Naming convention: `qa-*`._

Ví dụ slot:
- `qa-test-case-designer` — Sinh test case từ AC
- `qa-bug-report-template` — Template báo cáo bug có structured
- `qa-regression-suite-planner` — Plan suite regression cho feature mới

### 📋 BA / Business Analyst skills (chờ team BA add)

_Khi team BA gửi skills, sẽ list ở đây. Naming convention: `ba-*`._

Ví dụ slot:
- `ba-user-story-writer` — Sinh user story format chuẩn (As a / I want / So that)
- `ba-acceptance-criteria-template` — Template AC theo Given/When/Then
- `ba-requirement-extractor` — Bóc tách yêu cầu từ meeting note

---

## Workflow Chain — Skills work together

Skills không độc lập — chúng compose thành workflow. Ví dụ build 1 feature mới:

```
1. [BA] ba-user-story-writer
   ↓ Output: User story + AC
2. [Dev] tech-decision-framework
   ↓ Output: ADR chốt approach (vd: sync vs async)
3. [Dev] dev-baokim-db-schema-designer
   ↓ Output: Migration file mới
4. [Dev] dev-baokim-api-design
   ↓ Output: Route + Controller + Resource skeleton
5. [Dev] dev-baokim-laravel-repo-pattern
   ↓ Output: Repository + Service skeleton
6. [Dev] dev-baokim-sql-reviewer
   ↓ Output: Pre-merge review report
7. [QA] qa-test-case-designer
   ↓ Output: Test suite từ AC
```

→ Pattern em design: **skill là building block**, dev/QA/BA compose thành pipeline tự động hóa workflow công ty.

---

## Anthropic Standard Compliance

Skills tuân theo [Anthropic Claude Skill spec](https://code.claude.com/docs/en/skills) (Agent Skills open standard):

### Frontmatter chuẩn (Hybrid: Anthropic core + Baokim extensions)

```yaml
---
# Anthropic core (required)
name: <skill-name>            # kebab-case, max 64 chars
description: <1 câu cô đọng, ≤300 chars>

# Anthropic optional
when_to_use: |
  Triggers: <list trigger phrases>
  Anti-triggers: <KHÔNG dùng khi...>

# Baokim enterprise extensions
owner: <email>
version: 0.2.0
lifecycle: active | draft | beta
domain: cross | dev | qa | ba
created: YYYY-MM-DD
updated: YYYY-MM-DD
tags: [...]
---
```

→ Extras Baokim (`owner/version/lifecycle/domain/created/updated/tags`) không có trong Anthropic spec, nhưng YAML parser ignore không break. Show enterprise practice (ownership + versioning + lifecycle management).

### Folder structure (mỗi skill)

```
<skill-name>/
├── SKILL.md                    # Required — main entry
├── CHANGELOG.md                # Optional — version history
├── references/                 # Optional — lazy-load detail
│   ├── templates.md
│   └── examples.md
└── tests/
    └── prompts.md              # Optional — test cases
```

Xem [`PROGRESSIVE_DISCLOSURE.md`](PROGRESSIVE_DISCLOSURE.md) cho pattern Progressive Disclosure (Anthropic recommend khi SKILL.md > 250 lines).

---

## How to Invoke

### Cách 1 — Explicit (chắc 100%)

```
/<skill-name>
```

Ví dụ: `/dev-baokim-db-schema-designer`

### Cách 2 — Phrase matching (auto-trigger ~70-90%)

Type prompt có phrase trong `when_to_use`. Claude tự load skill match nhất.

Ví dụ:
- *"Tạo migration cho bảng `orders`"* → trigger `dev-baokim-db-schema-designer`
- *"Em phân vân queue driver — database hay Redis?"* → trigger `tech-decision-framework`
- *"Review SQL query này"* → trigger `dev-baokim-sql-reviewer`

### Cách 3 — Auto-trigger qua `CLAUDE.md` decision points

File `CLAUDE.md` root list explicit "khi gặp X → invoke skill Y". Claude đọc mỗi conversation → tự apply.

---

## Extension Guide — Cho Tester + BA

### Bước 1 — Naming convention

| Vai trò | Prefix | Ví dụ |
|---|---|---|
| QA/Tester | `qa-` | `qa-test-case-designer`, `qa-regression-planner` |
| BA | `ba-` | `ba-user-story-writer`, `ba-acceptance-criteria-template` |

### Bước 2 — Tạo folder theo cấu trúc

```bash
cd .claude/skills/
mkdir <prefix>-<area>-<purpose>     # vd: qa-test-case-designer
cd <name>/
touch SKILL.md
```

### Bước 3 — Frontmatter template

```yaml
---
name: <name>
description: <1 câu cô đọng "skill làm gì", ≤300 chars>
when_to_use: |
  Triggers: "<trigger phrase 1>", "<trigger phrase 2>", ...

  Anti-triggers (KHÔNG dùng skill này khi):
  - <case 1>
  - <case 2>
# Baokim enterprise extensions:
owner: <your-email>@baokim.vn
version: 0.1.0
lifecycle: draft
domain: qa | ba
created: YYYY-MM-DD
updated: YYYY-MM-DD
tags: [<tag1>, <tag2>, ...]
---

# <Skill Name>

## Mục đích
<Giải thích gap mà skill này fill — không trùng skill khác>

## Khi nào dùng / không dùng
✅ Use case 1
✅ Use case 2
❌ Anti-case 1
❌ Anti-case 2

## Workflow
### Step 1: ...
### Step 2: ...

## Examples
### Example 1: <concrete case>
**Input**: ...
**Output**: ...

## What NOT to do
❌ Anti-pattern 1
❌ Anti-pattern 2

## Reference foundation
- <Link to related skills hoặc references>

## Maintenance & Roadmap
- v0.1.0: initial
- v0.2.0: <planned>
```

### Bước 4 — Test skill load

```bash
cd /Users/duynv_1499/baokim/ocr-api
php artisan optimize:clear

# Mở Claude Code, gõ:
/<your-skill-name>

# Hoặc match qua trigger:
"<phrase trong when_to_use>"
```

### Bước 5 — Add vào catalog README

Update README.md section "QA Skills" hoặc "BA Skills" với mục mới của bạn.

### Pre-merge checklist trước commit

- [ ] Frontmatter có đủ `name` + `description` + `when_to_use`
- [ ] Description ≤ 300 chars (Anthropic cap 1536 combined)
- [ ] Body ≤ 500 lines (Anthropic recommend cap; nếu vượt → tách `references/`)
- [ ] Triggers + Anti-triggers rõ ràng — không vague
- [ ] ≥ 2 examples concrete (input + output cụ thể)
- [ ] "What NOT to do" section liệt kê anti-pattern
- [ ] Cross-reference skill khác nếu có handoff/escalation
- [ ] Test load bằng `/skill-name` — không YAML error

---

## Standards References

- [Anthropic Claude Skill spec](https://code.claude.com/docs/en/skills) — Official format spec
- [Agent Skills open standard](https://agentskills.io) — Cross-tool compatibility
- [`../references/baokim-db-standard.md`](../references/baokim-db-standard.md) — Baokim DB rules (BKM01-08, SCR, IDR, SQR)
- [`PROGRESSIVE_DISCLOSURE.md`](PROGRESSIVE_DISCLOSURE.md) — Pattern lazy-load detail
- [Project AC Compliance](../../docs/AC_COMPLIANCE.md) — Acceptance Criteria audit
- [Project README](../../README.md) — Overall architecture + tech decisions

---

## Story behind the ecosystem

Skill ecosystem **dynamic, không static**. Trong process build, em catch 8 DB violations theo BKM/SCR/IDR standards do AI dùng Laravel default `$table->timestamps()` (vi phạm SCR02.4). Root cause: skill `dev-baokim-sql-reviewer` chỉ trigger review-time, không gen-time.

→ Em build **skill 11** (`dev-baokim-db-schema-designer`) bịt gap gen-time + encode 8 trap Laravel default thành lesson learned cho AI tương lai.

→ Trong process design skill đó, em thấy thiếu skill chọn giữa N option → build **skill 12** (`tech-decision-framework`) cross-domain.

→ Pattern: **gap → catch → iterate skill → AI lần sau làm chuẩn hơn**. Skill 12 không phải con số cuối, là snapshot tại thời điểm submission.

→ Skill `feedback-to-skill-refiner` đóng vòng lặp này automatic — hằng tuần đọc log thao tác KSNB, đề xuất sửa skill khác.

---

## Maintenance

### Versioning

- `v0.1.0`: initial draft
- `v0.2.0`: aligned với code production + Anthropic spec compliance (current — 2026-05-25)
- `v0.3.0` (planned): tích hợp hooks Claude Code để force auto-trigger khi gen code
- `v1.0.0`: production-tested ≥ 30 PRs với skill activation

### Lifecycle

- `draft` — initial, chưa tested production
- `active` — đã verify, recommend dùng
- `beta` — testing mở rộng cross-team
- `deprecated` — không dùng nữa, replaced bởi skill khác

### Ownership

Mỗi skill có `owner` trong frontmatter. Owner chịu trách nhiệm:
- Update khi rule/pattern thay đổi
- Review PR liên quan skill
- Respond câu hỏi từ team khác về skill

---

## Acknowledgment

Skill ecosystem này là entry cho **Cuộc thi Claude Skill 2026 — Baokim**.

- **Lead Dev**: Duy ([duy@baokim.vn](mailto:duy@baokim.vn))
- **QA contributors**: _Pending_
- **BA contributors**: _Pending_

Pattern lấy cảm hứng từ:
- [Anthropic — Don't Build Agents, Build Skills Instead](https://www.youtube.com/watch?v=) (Barry Zhang, Mahesh Murag)
- [OpenAI — Harness Engineering](https://www.youtube.com/watch?v=) (Ryan Lopopolo)
- [Supabase — Combine Skills and MCP](https://www.youtube.com/watch?v=) (Pedro Rodrigues)
- [IBM — Harnesses in AI: A Deep Dive](https://www.youtube.com/watch?v=) (Tejas Kumar)

---

*Last updated: 2026-05-25 — Maintained by Duy*
