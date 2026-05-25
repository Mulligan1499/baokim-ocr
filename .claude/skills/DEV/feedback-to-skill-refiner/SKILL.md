---
name: feedback-to-skill-refiner
description: Phân tích implicit feedback user (action trên UI) → identify patterns "AI sai cùng field nhiều lần" → đề xuất diff cho skill prompt liên quan. Cross-domain — dùng được cho mọi LLM extraction pipeline có UI feedback (OCR/CV parsing/contract/customer feedback triage).
when_to_use: |
  Triggers: "analyze KSNB actions", "feedback to skill update", "propose skill diff
  from production data", "refine prompt from user actions", "ocr:analyze-actions
  command", "implicit feedback loop agent", "AI tự cải thiện từ user workflow".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Direct edit prompt file không qua review (human decision)
  - Tạo skill mới hoàn toàn (human-only — skill này chỉ refine existing)
  - Explicit feedback form analysis (skill này chỉ implicit actions)
  - Training custom ML model (đây là prompt engineering, không phải ML)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: cross
created: 2026-05-17
updated: 2026-05-25
tags: [feedback-loop, agent, skill-refinement, llm, prompt-engineering, ocr, baokim, semi-auto]
---

# Feedback-to-Skill Refiner

## Mục đích

Khép vòng "AI viết AI" cho dự án OCR Baokim KSNB:

1. **KSNB tương tác** với UI OCR result page (copy raw / edit then copy / skip / mark wrong) → action ghi vào `ocr_user_actions` (implicit feedback, không cần form).
2. **Agent gọi skill này** qua `php artisan ocr:analyze-actions` để aggregate patterns + sinh markdown diff đề xuất.
3. **Engineer review** qua GitHub PR — approve để merge skill update, hoặc reject với reason.
4. **Skill update** → next pipeline run dùng prompt cải thiện → loop khép kín.

**Reference foundation**:
- `cross-llm-extraction-prompt`: 7 anti-hallucination patterns (foundation cho mọi extraction skill)
- `dev-vision-prompt-designer`: skill được refine nhiều nhất (Stage 2 extractor — nơi AI bịa nhiều nhất)

## Khi nào dùng skill này

✅ Agent run weekly/monthly để analyze KSNB actions tuần qua
✅ Sau khi deploy skill update, theo dõi xem feedback có giảm không
✅ Adapt cho domain khác có implicit feedback (CV parsing với hire/reject action, contract extract với approve/redline action)

❌ Tạo skill mới (human-only — skill design cần judgment)
❌ Auto commit không qua human review (cuộc thi yêu cầu human keep loop)
❌ Train custom ML model từ feedback (đây là prompt engineering, không phải ML pipeline)
❌ Phân tích explicit feedback form (dùng generic survey analysis skill)

## Decision tree: pattern → target artifact

Quy định rõ scope agent được phép update để tránh skill bloat + safety.

| Pattern observed | Categorize | Target update | Severity |
|---|---|---|---|
| Field X có ≥10 `edit_then_copy` (original ≠ final) cùng doc_type | A — Extract format wrong | `.claude/skills/dev-vision-prompt-designer/SKILL.md` (knowledge) + `resources/prompts/stage2_vision.md` (runtime) | HIGH |
| Doc type bị reclassify (KSNB upload lại với hint khác doc_type) | B — Classifier confusion | `.claude/skills/ocr-document-classifier/SKILL.md` + `resources/prompts/stage1_classifier.md` | MEDIUM |
| Field X có ≥5 `mark_wrong` (hard signal) | C — AI hallucinated | Cả A + tăng confidence threshold cho field trong `config/ocr.php` | HIGH |
| Validator pass nhưng KSNB edit → false negative | D — Validation rule lax | `.claude/skills/ocr-extraction-validator/SKILL.md` + `app/Services/Ocr/Stage3RuleValidator.php` (regex) | MEDIUM |
| Field skip rate >50% | E — Field không relevant | Drop field khỏi critical_fields trong `dev-vision-prompt-designer` taxonomy | LOW |
| `duration_ms > 30s` trên field confidence ≥0.9 + ≥5 occurrences | F — Confidence over-calibrated | `.claude/skills/ocr-confidence-aggregator/SKILL.md` (justify threshold) + `config/ocr.php` (threshold) | MEDIUM |
| PII miss (KSNB edit để mask thêm) | G — PII pattern thiếu | `.claude/skills/vn-pii-masker/SKILL.md` + `app/Services/Ocr/Stage4PiiMaskerService.php` (regex) | HIGH |
| Pattern ngoài 7 categories trên | H — Out of scope | Output trong PR: "Need new skill — assign to human" + suggest skill name + scope | n/a |

**Quan trọng — agent NEVER tạo skill mới:**
- Skill design = strategic decision (taxonomy, reusability, anti-trigger) cần human judgment
- Cuộc thi yêu cầu dev tự thiết kế skill — nếu agent tạo thì mất point đánh giá
- Risk skill bloat overlap (1 năm sau có 50 skills không ai dùng)

## Workflow

### Step 1: Aggregate actions

SQL aggregation join `ocr_user_actions` + `ocr_extractions` để có context doc_type:

```sql
SELECT
    e.doc_type,
    a.field_key,
    a.action_type,
    COUNT(*) as cnt,
    AVG(a.duration_ms) as avg_duration_ms,
    SUM(CASE WHEN a.original_value <> a.final_value THEN 1 ELSE 0 END) as edited_count
FROM ocr_user_actions a
LEFT JOIN ocr_extractions e ON e.document_id = a.document_id
WHERE a.analyzed_at IS NULL
  AND a.created_at >= :since
GROUP BY e.doc_type, a.field_key, a.action_type
HAVING cnt >= :min_count
ORDER BY cnt DESC;
```

Filter theo `analyzed_at IS NULL` → tránh re-process actions đã phân tích lần trước.

### Step 2: Pattern detection rules

Per row aggregation, classify theo bảng decision tree trên. Một row có thể trigger nhiều rules.

```python
patterns = []
for row in aggregated:
    cnt = row['cnt']
    edited = row['edited_count']
    edit_rate = edited / cnt if cnt else 0
    avg_dur = row['avg_duration_ms']
    action = row['action_type']
    field = row['field_key']
    doc_type = row['doc_type']

    # Rule A: extract format wrong
    if action == 'edit_then_copy' and edited >= 10 and edit_rate > 0.7:
        patterns.append(pattern_a(field, doc_type, cnt, edited))

    # Rule C: hallucinated (hard signal)
    if action == 'mark_wrong' and cnt >= 5:
        patterns.append(pattern_c(field, doc_type, cnt))

    # Rule E: field not relevant
    if action == 'skip' and cnt >= 20:
        patterns.append(pattern_e(field, doc_type, cnt))

    # Rule F: confidence over-calibrated
    if avg_dur > 30000 and cnt >= 5:  # > 30s
        patterns.append(pattern_f(field, doc_type, cnt, avg_dur))
```

### Step 3: Call LLM (Claude/Gemini) để generate diff

Cho each pattern, agent gọi LLM với prompt + few-shot example. LLM trả về JSON:

```json
{
  "pattern_id": "A-cccd-so_cccd-001",
  "category": "A",
  "observation": "Trong 12 lần KSNB extract CCCD, field `so_cccd` bị edit_then_copy 10 lần (83%). 4 lần KSNB chỉ thêm leading zero, 6 lần sửa digit cuối — model OCR thường confuse 0/O ở chữ cuối.",
  "hypothesis": "Prompt Stage 2 thiếu hint về CCCD pattern leading zero + ambiguous chars 0/O ở cuối. Model thấy 'O' trong chữ tiếng Việt cao bên cạnh số nên đoán là chữ.",
  "target_skill_file": ".claude/skills/dev-vision-prompt-designer/SKILL.md",
  "target_runtime_file": "resources/prompts/stage2_vision.md",
  "proposed_diff": "--- a/resources/prompts/stage2_vision.md\n+++ b/resources/prompts/stage2_vision.md\n@@ Critical rules:\n+8. CCCD field-specific: digits 0/O dễ confuse với chữ Việt 'O'. Cross-check format `^\\d{12}$` strict. Nếu đọc được < 12 digits → return value=\"\" thay vì padding. Leading zero phải giữ — số tỉnh đầu đa số 0xx.\n",
  "confidence": 0.85,
  "suggested_action": "create_pr"
}
```

Critical patterns trong prompt:
- AH-1 (empty thay vì guess) áp dụng cho diff generation: agent KHÔNG bịa diff nếu pattern không rõ → trả `suggested_action: "needs_more_data"`
- AH-4 (reasoning trace): mỗi pattern có observation + hypothesis riêng, không bỏ qua giải thích

### Step 3.5: Confidence threshold → suggested_action mapping

Field `confidence` trong output (0.0-1.0) → quyết định mức độ tự động hóa. Agent dùng table dưới để chọn `suggested_action`:

| Confidence | suggested_action | Engineer behavior | Tỉ lệ false positive chấp nhận |
|---|---|---|---|
| **> 0.8** | `create_pr` | Agent tự tạo PR draft, assign reviewer, send Slack notify | < 10% (1/10 PR có thể reject) |
| **0.6 - 0.8** | `review_first` | Surface trong CLI report + Slack daily digest, KHÔNG tự tạo PR | < 30% |
| **0.4 - 0.6** | `needs_more_data` | Log to drafts, agent gợi ý collect thêm 1 tuần data | < 50% — nhiều noise |
| **< 0.4** | `skip` | Discard pattern, không action | — pattern không đủ tin cậy |

**Special cases**:
- Pattern category G (security/PII issue) → upgrade `confidence × 1.2`, min `create_pr` ngay cả khi raw confidence ~0.7
- Pattern category H (needs new skill) → `suggested_action = "needs_new_skill"` thay vì PR — human-only decision
- Có ≥ 3 KSNB user agreeing (same pattern, different sessions) → upgrade lên `create_pr` regardless of base confidence

**Tại sao 0.8 / 0.6 / 0.4 thresholds**:
- 0.8 = "rõ ràng có pattern" — typical 8+ occurrences, edit rate > 70%, semantic match high
- 0.6 = "có pattern nhưng chưa chắc do data thưa" — 4-6 occurrences hoặc edge case
- 0.4 = "có thể là noise" — < 4 occurrences hoặc mixed signal
- Threshold calibrate sau 4 tuần production data → adjust per pattern category

### Step 4: Output 1 markdown report

Sau khi xử lý hết patterns, agent sinh file `storage/reports/skill-update-YYYY-MM-DD-HHMMSS.md`:

```markdown
# Skill Update Report — 2026-05-25

Analyzed 234 KSNB actions over 7 days. Found 3 actionable patterns.

## Pattern A-cccd-so_cccd-001 (HIGH severity)
**Observation:** ...
**Hypothesis:** ...
**Target:** `.claude/skills/dev-vision-prompt-designer/SKILL.md` + `resources/prompts/stage2_vision.md`
**Confidence:** 0.85

### Proposed diff:
```diff
--- a/resources/prompts/stage2_vision.md
+++ b/resources/prompts/stage2_vision.md
@@ -45,6 +45,8 @@
 7. CCCD field-specific: digits 0/O dễ confuse...
+8. New rule from feedback...
```

### Engineer action:
- [ ] Apply diff to runtime prompt
- [ ] Update skill knowledge file
- [ ] Reject — reason: ___

## Pattern B-... (MEDIUM)
...

## Out-of-scope (assigned to human):
- Pattern X-...: 5 occurrences of "field Y mismatched between zh/vi translation" — không match category nào. Đề xuất human design skill `vn-zh-translation-validator`.
```

### Step 5: Tạo GitHub PR (semi-auto)

Sau khi engineer chạy `php artisan ocr:analyze-actions`:

1. Service `ActionAnalyzerService::proposeSkillUpdate()` trả patterns + diffs
2. Service `GitHubPrCreator::create()`:
   - `git checkout -b agent/skill-update-2026-05-25-145200`
   - Apply diffs vào target files
   - `git add .claude/skills/.../SKILL.md resources/prompts/...md`
   - `git commit -m "[ai-major] skill: refine N patterns from KSNB feedback"`
   - `git push origin agent/skill-update-...`
   - `gh pr create --title "[Skill update] N patterns from KSNB actions" --body "..."`
3. Output PR URL cho engineer mở review
4. Mark `analyzed_at = NOW()` cho all rows processed (idempotent — re-run không double-count)

## Examples

### Example 1: CCCD checksum miss (Pattern A — Extract format wrong)

**Input data:**
- 12 actions cho `field_key=so_cccd, doc_type=cccd, action_type=edit_then_copy`
- 10/12 edits (83%)
- Avg duration 8s (normal)

**Agent output JSON:**

```json
{
  "pattern_id": "A-cccd-so_cccd-001",
  "category": "A",
  "observation": "field so_cccd doc_type cccd có 12 actions, 10 edit_then_copy (83% edit rate). Patterns chính: thêm leading zero (4×), sửa digit cuối confuse 0/O (6×).",
  "hypothesis": "Stage 2 prompt thiếu CCCD strict regex + warning về 0/O confusion ở cuối.",
  "target_skill_file": ".claude/skills/dev-vision-prompt-designer/SKILL.md",
  "target_runtime_file": "resources/prompts/stage2_vision.md",
  "proposed_diff": "diff text...",
  "confidence": 0.85,
  "suggested_action": "create_pr"
}
```

### Example 2: Field skip rate cao (Pattern E — Field not relevant)

**Input data:**
- 25 actions cho `field_key=ethnicity, doc_type=cccd, action_type=skip`
- KSNB skip 25/25 (100%) — không bao giờ dùng field này

**Agent output:**

```json
{
  "pattern_id": "E-cccd-ethnicity-001",
  "category": "E",
  "observation": "field ethnicity bị skip 100% (25/25 actions). KSNB không bao giờ copy field này vào hệ thống Baokim — có vẻ không relevant cho onboarding workflow.",
  "hypothesis": "ethnicity là field optional trong CCCD nhưng KSNB workflow không cần. Có thể remove khỏi critical_fields → giảm prompt size, tiết kiệm tokens, focus model vào fields KSNB thực sự dùng.",
  "target_skill_file": ".claude/skills/dev-vision-prompt-designer/SKILL.md",
  "target_runtime_file": null,
  "proposed_diff": "remove 'ethnicity' from CRITICAL_FIELDS cccd taxonomy",
  "confidence": 0.92,
  "suggested_action": "create_pr"
}
```

### Example 3: Out-of-scope pattern (Pattern H — Need new skill)

**Input:** 8 actions với pattern không match category nào — KSNB skip translation_vi vì sai semantic ngữ pháp tiếng Việt cho ZH source.

**Agent output:**

```json
{
  "pattern_id": "H-translation-quality-001",
  "category": "H",
  "observation": "8 lần KSNB skip translation_vi field cho doc_type=contract_zh. Pattern: bản dịch tiếng Việt sai cấu trúc câu (giữ word order ZH).",
  "hypothesis": "Cần skill dedicated cho ZH→VI translation quality validation. Hiện tại skill `dev-vision-prompt-designer` chỉ encode AH-6 (translation grounding) nhưng không có rules về VN grammar/syntax.",
  "target_skill_file": null,
  "target_runtime_file": null,
  "proposed_diff": null,
  "confidence": 0.7,
  "suggested_action": "needs_new_skill",
  "suggested_skill_name": "vn-zh-translation-validator",
  "suggested_skill_scope": "Validate ZH→VI translation quality, focus on VN grammar/syntax, idiom mapping, name romanization Hán-Việt."
}
```

→ Agent KHÔNG tạo skill mới. Output trong PR description: "Pattern H-001 needs human attention — suggest skill `vn-zh-translation-validator` (see scope above). Assigning to @duy."

### Example 4: Reusability — HR CV parsing

Adapt cho domain khác, đổi input source:

- `ocr_user_actions` → `hr_cv_user_actions`
- doc_types: `cv_vi`, `cv_en`, `application_letter`
- Field examples: `years_experience`, `email`, `current_company`, `skills`
- Action types same: copy_raw, edit_then_copy, skip, mark_wrong

Agent workflow + decision tree không đổi. Chỉ đổi target skill file path (`dev-hr-cv-parser` thay vì `dev-vision-prompt-designer`).

→ Bằng chứng reusability: pattern detection generic, decision tree map theo domain.

## What NOT to do

❌ KHÔNG tự commit/push mà không tạo PR — vi phạm semi-auto loop, mất safety
❌ KHÔNG tạo skill mới (category H pattern) — human-only decision
❌ KHÔNG override existing skill examples mà không preserve original intent
❌ KHÔNG generate diff khi pattern không rõ (cnt < min_count, edit_rate < 50%) — trả `needs_more_data`
❌ KHÔNG ignore Category H — phải surface để human attention, không silent fail
❌ KHÔNG aggregate cùng `analyzed_at IS NOT NULL` actions — re-process double count
❌ KHÔNG run agent với data < 7 ngày — pattern noise quá lớn, false positive
❌ KHÔNG generate >5 patterns 1 lần — engineer overwhelmed; cap 5 patterns, sort by confidence

## Reusability angle

Generic semi-auto feedback loop pattern, dùng cho:

- **OCR (current)** — KSNB upload + interact với extracted fields
- **HR CV parsing** — recruiter accept/reject ứng viên, edit field trước khi import ATS
- **Contract extraction** — legal team redline fields trước khi save
- **Customer feedback triage** — analyst edit category/sentiment trước khi route
- **Email classification** — user move email giữa folders (implicit feedback)
- **Translation QA** — reviewer edit translation trước khi publish

Cùng workflow: track implicit actions → aggregate → categorize per decision tree → propose diff → GitHub PR → human approve.

## Reference foundation

- `cross-llm-extraction-prompt`: 7 anti-hallucination patterns (foundation cho diff generation)
- `dev-vision-prompt-designer`: skill được refine nhiều nhất (Stage 2 extractor)
- `dev-baokim-laravel-repo-pattern`: PHP service skeleton cho ActionAnalyzerService

## Maintenance & Roadmap

- v0.2.0: thêm `pattern_severity` ranking + auto-prioritize PR order
- v0.3.0: support Anthropic API ngoài Gemini (provider-agnostic qua LlmClient interface)
- v0.4.0: skill self-test — sinh fake actions để verify decision tree correctness
- v1.0.0 (active): 100+ patterns analyzed production, false positive rate < 10%

Tests: `tests/Unit/Services/Feedback/ActionAnalyzerServiceTest.php`
