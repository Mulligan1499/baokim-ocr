---
name: tech-decision-framework
description: Cross-domain skill phân tích trade-off + chọn giải pháp kỹ thuật khi có 2-N approach khả thi. Encode 7-step workflow → output ADR (Architecture Decision Record) commit-ready. Reusable cho mọi tech stack (vendor, library, architecture, pattern, infra).
when_to_use: |
  Triggers: "nên dùng X hay Y", "chọn giữa [A] và [B]", "phân tích trade-off",
  "đánh giá option [X]", "so sánh giải pháp", "X vs Y vs Z", "approach nào hợp lý",
  "library nào tốt hơn", "kiến trúc nào phù hợp", "ADR cho [quyết định]",
  "decision record cho", "phân vân giữa", "weighing options", "tech stack cho",
  "có nên dùng [X] không".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Chỉ có 1 option duy nhất (implementation, không phải trade-off)
  - Apply standard có sẵn → dùng `dev-baokim-*` skills
  - Code gen thuần không có decision (fix typo, rename var)
  - Bug fix với root cause rõ ràng (không phải decision, là debug)
  - Câu hỏi học thuật/khái niệm không có context dự án (vd "OOP vs FP là gì")
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: cross
created: 2026-05-24
updated: 2026-05-25
tags: [decision-making, architecture, trade-off, adr, engineering, cross-domain]
---

# Tech Decision Framework

## Mục đích

Skill này bịt **gap thường ngày của dev** mà `dev-baokim-*` skills chưa cover: phân tích trade-off khi có nhiều approach khả thi và cần chọn 1.

**Phân biệt với skills khác**:

| Tình huống | Skill phù hợp |
|---|---|
| "Đã chốt dùng Eloquent rồi, viết Repository code" | `dev-baokim-laravel-repo-pattern` |
| "Đã chốt schema, viết migration" | `dev-baokim-db-schema-designer` |
| "Code SQL đã viết, review hộ" | `dev-baokim-sql-reviewer` |
| **"Nên dùng Eloquent eager load hay raw JOIN cho query này?"** | **`tech-decision-framework`** ← skill này |
| **"Cache layer Redis vs Memcached cho project?"** | **`tech-decision-framework`** |
| **"Queue driver: database vs Redis vs SQS?"** | **`tech-decision-framework`** |
| **"Sync vs async cho OCR pipeline?"** | **`tech-decision-framework`** |

→ Skill `dev-baokim-*` apply rule khi **đã chốt giải pháp**. Skill này dùng để **chốt giải pháp**.

## Khi nào dùng

✅ Đứng giữa 2-5 option kỹ thuật khả thi, cần chốt 1
✅ Quyết định có trade-off (perf vs cost, simple vs scale, vendor lock-in vs flexibility)
✅ Cần document decision cho team (ADR)
✅ Decision khó reverse (vendor choice, schema design, architecture)
✅ Onboard team member mới cần hiểu vì sao chọn approach hiện tại

❌ Chỉ có 1 cách làm duy nhất (đó là implementation, không phải decision)
❌ Apply standard có sẵn — Baokim convention → dùng `dev-baokim-*`
❌ Bug fix với root cause rõ → fix luôn, không phải decision
❌ Code refactor thuần (đổi tên, tách function) → không cần framework

## Workflow 7 bước

### Bước 1 — Framing (define problem cứng)

Trước khi list option, **define problem rõ ràng**. Câu hỏi cần trả lời:

| Khía cạnh | Câu hỏi |
|---|---|
| **Problem statement** | Bài toán đang giải là gì? 1 câu. |
| **Why now** | Vì sao phải quyết bây giờ? (deadline, scale up, compliance, migration) |
| **Constraints cứng** | Deadline, budget, team size, team skill, vendor có sẵn không thay đổi |
| **Constraints mềm** | Preference (vd: "team thích Laravel" — có thể bend) |
| **Reversibility** | Đổi sau dễ (reversible) hay khó (irreversible/lock-in)? |
| **Time horizon** | Quyết định valid trong 6 tháng / 2 năm / 5 năm? |
| **Success criteria** | Đạt được gì là OK? (p99 latency < 100ms? cost < $500/tháng?) |

**Output bước 1**: 1 paragraph framing ≤ 8 dòng.

### Bước 2 — List options (2-5)

User liệt kê hoặc skill tự suggest. Quy tắc:
- **Tối thiểu 2 options** (1 option = không có trade-off, skip skill)
- **Tối đa 5 options** (nhiều hơn → analysis paralysis, gộp/loại trước)
- Mỗi option có **1-line description** rõ ràng
- Bao gồm **"do nothing / keep status quo"** nếu có thể

**Output bước 2**: bảng options.

### Bước 3 — Define tiêu chí đánh giá (5-7 categories)

Chọn 5-7 tiêu chí relevant với problem. Defaults phổ biến:

| Tiêu chí | Khi nào quan trọng |
|---|---|
| **Performance** (latency, throughput) | High-traffic system, real-time |
| **Cost** (infra, license, dev time) | Budget constraint, startup |
| **Operational burden** (monitoring, backup, scale-up) | Small team, no SRE |
| **Team skill match** | Skill gap → learning cost cao |
| **Vendor lock-in / exit cost** | Reversibility quan trọng |
| **Maturity** (community, docs, ecosystem) | Risk-averse, production-critical |
| **Security** (PII, compliance, audit) | Fintech, healthcare |
| **Compliance** (regulation specific) | Banking, GDPR |
| **Time to deliver** (setup + integration) | Tight deadline |
| **Backward compatibility** | Existing system, migration risk |

Chọn 5-7 cái relevant. KHÔNG dùng > 7 → matrix khó đọc.

**Output bước 3**: list tiêu chí + giải thích why each chọn.

### Bước 4 — Build trade-off matrix

Bảng options × criteria với scoring:

**Scoring system**:
- ⭐⭐⭐⭐⭐ (5) — Xuất sắc, best-in-class
- ⭐⭐⭐⭐ (4) — Tốt
- ⭐⭐⭐ (3) — Trung bình, đủ dùng
- ⭐⭐ (2) — Yếu nhưng chấp nhận được
- ⭐ (1) — Vấn đề lớn

**Cost notation** (riêng cost):
- $ = rẻ / < $50/tháng / < 1 ngày dev
- $$ = trung bình / $50-500/tháng / 1-5 ngày dev
- $$$ = đắt / > $500/tháng / > 5 ngày dev

**Quan trọng**: KHÔNG dùng số (0-100). Quá chính xác → false precision, dev tranh cãi vì "tại sao 87 không phải 88". Stars + dollar signs đủ truyền ý.

**Output bước 4**: bảng matrix.

### Bước 5 — Recommendation kèm reasoning

Format:

```markdown
### Recommend: [Option X]

**3 lý do chính**:
1. [Lý do then chốt 1, kèm specific metric/constraint]
2. [Lý do 2]
3. [Lý do 3]

**Trade-off chấp nhận**: 
- Con của X: [liệt kê 2-3 điểm yếu]
- Đổi lại: [lợi ích chính]

**Khi nào KHÔNG chọn X** (escape hatch):
- Nếu [điều kiện] → switch sang [Option Y]
- Nếu [điều kiện] → switch sang [Option Z]
```

**Quy tắc**: ≤ 3 lý do chính. Nhiều hơn → có thể đang rationalize chứ không phải reasoning.

### Bước 6 — Risk + rollback plan

3 phần:

**A. Top 3 rủi ro lớn nhất** khi chọn option recommend:

| # | Rủi ro | Probability | Impact | Mitigation |
|---|---|---|---|---|
| 1 | [Rủi ro 1] | Low/Med/High | Low/Med/High | [Cách giảm thiểu] |
| 2 | [Rủi ro 2] | ... | ... | ... |
| 3 | [Rủi ro 3] | ... | ... | ... |

**B. Early warning signals** — metric/event nào báo hiệu quyết định đang fail:
- Signal 1: vd "Queue depth > 1000 ổn định" → quyết định queue driver sai
- Signal 2: vd "Error rate > 5%" → vendor không đáp ứng SLA
- Signal 3: vd "Team complain 2+ lần/tuần" → cognitive overhead cao

**C. Rollback plan**:
- Reversible: Cost rollback (giờ làm việc + $)
- Steps cụ thể: 1, 2, 3...
- Data migration risk: có lose data không?

### Bước 7 — Output ADR (Architecture Decision Record)

ADR là **format chuẩn industry** để document decision. Skill này gen ADR ready commit vào `docs/adr/`.

Template:

```markdown
# ADR-NNNN: [Title — Decision in 1 line]

**Status**: Proposed | Accepted | Deprecated | Superseded by ADR-XXX
**Date**: YYYY-MM-DD
**Decider**: [Tên người quyết / team]
**Stakeholders**: [Tên những người ảnh hưởng quyết định]

## Context

[1-2 paragraph framing problem + constraints. Trả lời: tại sao có decision này?]

## Decision

[1 paragraph mô tả option chọn + 3 lý do chính.]

## Consequences

**Positive**:
- [Lợi ích 1]
- [Lợi ích 2]

**Negative**:
- [Trade-off 1]
- [Trade-off 2]

**Neutral**:
- [Tác động không hẳn tốt/xấu nhưng cần biết]

## Alternatives considered

### [Option A]
[1 paragraph: pros + cons + lý do không chọn]

### [Option B]
[Tương tự]

## Risks

[Top 3 risks + mitigation từ bước 6]

## Rollback plan

[Steps từ bước 6]

## References

- [Link benchmark, doc, prior ADR liên quan]
```

Filename convention: `docs/adr/NNNN-kebab-case-title.md` (vd: `0001-queue-driver-database-vs-redis.md`).

## ADR template — copy ready

Skill khi gen ADR sẽ output 1 file markdown đầy đủ. Filename suggest từ title.

## Examples

### Example 1: Queue driver — database vs Redis vs SQS

**Input user**:
> "Em đang phân vân queue driver cho project OCR Baokim. Hiện dùng database driver (queue table trong MySQL), có nên chuyển Redis hay không?"

**Skill output**:

```markdown
## Bước 1 — Framing

**Problem**: Chọn queue driver cho pipeline OCR (job ProcessOcrDocument).

**Why now**: Project sắp launch production sau cuộc thi, cần đảm bảo
scale + ops simplicity cho 1 dev maintenance.

**Constraints**:
- Cứng: 1 dev (Duy) maintain, budget < $20/tháng infra extra
- Mềm: ưu tiên solution có sẵn trong Laravel ecosystem
- Reversibility: rất dễ — chỉ đổi env QUEUE_CONNECTION, driver swap

**Time horizon**: 6-12 tháng đầu sau launch. Sau đó re-evaluate khi
volume scale 10×.

**Success criteria**: 
- Queue depth p99 < 100 ổn định
- Job latency p95 < 5s (từ dispatch đến worker pick up)
- Ops burden < 30 phút/tuần

## Bước 2 — Options

| Option | 1-line description |
|---|---|
| A. Database (hiện tại) | Laravel `database` driver, queue table trong MySQL `bk_automation` |
| B. Redis | Self-host Redis trên server hiện tại, Laravel `redis` driver |
| C. SQS | AWS SQS managed queue, Laravel `sqs` driver |
| D. Do nothing | Giữ database driver, accept limitation hiện tại |

## Bước 3 — Tiêu chí (6)

1. **Throughput** — Job/giây hệ thống xử lý được
2. **Cost** — Infra + license + dev time
3. **Operational burden** — Setup, monitor, backup, scale-up
4. **Team skill match** — Familiarity với tech
5. **Reversibility** — Đổi sang option khác dễ không
6. **Time-to-deploy** — Bao lâu để live

## Bước 4 — Matrix

| Tiêu chí | A. Database | B. Redis | C. SQS | D. Status quo |
|---|---|---|---|---|
| Throughput | ⭐⭐⭐ (~100/min) | ⭐⭐⭐⭐⭐ (>1000/min) | ⭐⭐⭐⭐⭐ (unlimited) | ⭐⭐⭐ |
| Cost | $ ($0) | $ ($5-10/tháng) | $ ($0.40/1M msg) | $ ($0) |
| Operational | ⭐⭐⭐⭐ (low) | ⭐⭐ (cần monitor Redis) | ⭐⭐⭐⭐⭐ (managed) | ⭐⭐⭐⭐ |
| Team skill | ⭐⭐⭐⭐⭐ (đang dùng) | ⭐⭐⭐ (cần học monitor) | ⭐⭐ (AWS không quen) | ⭐⭐⭐⭐⭐ |
| Reversibility | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ (lock-in AWS) | ⭐⭐⭐⭐⭐ |
| Time-to-deploy | ⭐⭐⭐⭐⭐ (sẵn) | ⭐⭐⭐ (2h setup) | ⭐⭐⭐ (1h setup + IAM) | ⭐⭐⭐⭐⭐ |

## Bước 5 — Recommendation: A. Database driver (giữ nguyên)

**3 lý do chính**:
1. Volume thực tế 40 docs/ngày = 0.03 jobs/min — Database thừa sức (cap 100/min)
2. Ops simplicity: 1 dev không nên maintain Redis cho 40 jobs/ngày → ROI âm
3. Reversibility: đổi sang Redis sau 1 giờ nếu real-time vượt 50/min

**Trade-off chấp nhận**:
- Con: throughput ceiling thấp hơn Redis 10× → không scale > 50/min
- Đổi lại: zero ops burden, dev focus vào feature thay vì infra

**Khi nào CHUYỂN sang Redis** (escape hatch):
- Volume real-time > 30 jobs/min ổn định (queue depth > 100)
- Cần job priority queue (Database không hỗ trợ tốt)
- Cần distributed lock (Redis có sẵn primitive)

## Bước 6 — Risks

| # | Rủi ro | P | I | Mitigation |
|---|---|---|---|---|
| 1 | Queue table grow lớn → MySQL slow | Low | Med | Monitor `jobs` table size weekly, alert nếu > 10k pending |
| 2 | Worker crash → job stuck `processing` | Med | Low | Laravel default `--tries=3` + `failed_jobs` table |
| 3 | Spike upload (demo) vượt cap 100/min | Low | Med | Pre-demo: rate-limit upload UI tại frontend |

**Early warning signals**:
- Queue depth > 100 ổn định trong 7 ngày
- Job latency p95 > 10s
- KSNB complain "upload chậm phản hồi"

**Rollback plan** (nếu cần chuyển Redis):
1. Setup redis-server (apt install + config) — 30 phút
2. composer require predis/predis — 5 phút
3. Update .env: `QUEUE_CONNECTION=redis`, `REDIS_*` — 5 phút
4. Restart queue:work workers — 1 phút
5. Migrate pending jobs từ database queue: artisan command custom — 30 phút
6. Total: ~1.5 giờ. Data migration risk: low (queue jobs ephemeral).

## Bước 7 — ADR

`docs/adr/0001-queue-driver-database-vs-redis.md`:

[ADR file đầy đủ format chuẩn]
```

### Example 2: Eloquent eager load vs raw JOIN

**Input user**:
> "Em có query lấy 100 documents kèm extractions kèm texts. Nên dùng Eloquent `with(['extraction.text'])` hay raw JOIN?"

**Skill output highlights** (rút gọn):

- Tiêu chí: performance, code clarity, BKM02 compliance (Eloquent only), maintainability
- Options: A. Eager load Eloquent, B. Raw JOIN với DB::table()
- Recommend: A. Eager load — BKM02 cấm DB::table() standalone (anti-trigger với raw JOIN), performance đủ với ~100 docs, code clearer.
- Khi NÀO chuyển raw: nếu query > 1000 rows + có complex aggregation Eloquent không expressive → exception BKM02 với `DB::raw()` inside model scope.

→ Skill này cũng **escalate ngược về `dev-baokim-laravel-repo-pattern`** nếu liên quan BKM02.

### Example 3: Schema choice — 1 bảng JSON vs nhiều bảng normalized

**Input user**:
> "Lưu user preference. Nên 1 bảng `users` với cột `preferences JSON` hay tách bảng `user_preferences` riêng?"

**Skill output highlights**:

- Tiêu chí: query flexibility, schema evolution, indexing, BKM convention
- Options: A. JSON column, B. Normalized table
- Recommend: phụ thuộc — nếu preferences schema thay đổi thường + ít query filter theo preference value → JSON OK. Nếu cần index hoặc query theo preference value → tách bảng.
- Escalate sang `dev-baokim-db-schema-designer` nếu chọn option tách bảng để apply SCR/IDR.

### Example 4: Sync vs async architecture (OCR pipeline)

**Input user**:
> "Pipeline OCR hiện đang sync — request đợi 30s. Production có nên chuyển async với polling?"

**Skill output**:

- Tiêu chí: UX (response time), client complexity, server load, debug ease
- Options: A. Sync (hiện tại), B. Async với polling, C. Async với webhook
- Matrix...
- Recommend: B. Async polling với placeholder response 202 — match AC-01 latency target + KSNB workflow chấp nhận, complexity vừa phải.
- Risk: client phải implement retry polling logic → mitigation document trong Swagger.

## What NOT to do

❌ KHÔNG output recommendation mà thiếu reasoning specific (3 lý do)
❌ KHÔNG dùng > 7 tiêu chí — matrix khó đọc, decision paralysis
❌ KHÔNG dùng số chính xác (87/100) — false precision, dev tranh cãi → dùng stars
❌ KHÔNG skip bước 6 (risks) — quyết định không có rollback plan là quyết định ẩn rủi ro
❌ KHÔNG chọn option A "vì nó là default" — phải justify (option mặc định cũng cần phân tích)
❌ KHÔNG output mà không có ADR — quyết định mà không document = team không reproducible
❌ KHÔNG apply skill này cho việc bug fix / refactor đơn thuần — đó không phải decision

## When to escalate / chain skills

Sau khi skill này quyết → có thể chain sang skill khác để implement:

| Quyết định | Next skill |
|---|---|
| Chọn schema design | `dev-baokim-db-schema-designer` |
| Chọn Repository/Service pattern | `dev-baokim-laravel-repo-pattern` |
| Chọn API design | `dev-baokim-api-design` |
| Chọn SQL approach | `dev-baokim-sql-reviewer` (review) |
| Chọn LLM prompt design | `cross-llm-extraction-prompt` |

→ Skill chain: `tech-decision-framework` (decide) → `dev-baokim-*` (implement).

## Reference foundation

- ADR pattern: <https://adr.github.io/>
- "Architecture Decision Records — How they fit into the bigger picture" (Michael Nygard, 2011)
- Internal: project sẽ có `docs/adr/` folder để lưu mọi ADR

## Maintenance & Roadmap

- v0.1.0 (current): 7-step workflow + 4 examples cross-domain
- v0.2.0: thêm 3 example domain non-Laravel (Go service, Python data pipeline, React state mgmt)
- v0.3.0: tích hợp Plan tool — auto-create plan từ ADR output
- v0.4.0: cost calculator (skill prompt user input volume + pricing → output $/tháng cho mỗi option)
- v1.0.0: production-tested ≥ 20 ADR commits real projects

Tests: `tests/prompts.md`
