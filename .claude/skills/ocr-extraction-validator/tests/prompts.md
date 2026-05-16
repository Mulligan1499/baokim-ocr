# Test prompts: ocr-extraction-validator

## Trigger tests (skill SHOULD activate)

1. "Design Stage 3 Validator cho harness OCR"
2. "Rule-based validator + LLM judge separate call"
3. "Validate key_values output từ Stage 2 — anti-bias dương"
4. "Build Stage3ValidatorService Laravel"
5. "Validation logic cho CCCD/MST/phone/date trong extraction"
6. "LLM-as-judge prompt cho extraction sanity check"
7. "Independent validator pattern cho LLM extraction"
8. "Stage 3 cho OCR project Baokim"

## Anti-trigger tests (skill should NOT activate)

1. "Form request validation Laravel" — Stage 0 hoặc Laravel FormRequest
2. "Test LLM accuracy bằng golden set" — QA skill
3. "Aggregate confidence từ multiple signals" — Stage 5 dùng `ocr-confidence-aggregator`
4. "JSON schema validation" — không cần LLM
5. "Validate user input frontend form" — frontend concern

## Output quality tests

### OQ-1: 2 sub-stage separation
**Input**: "Design Stage 3 cho OCR project"

**Expected output**:
- 3a Rule-based: standalone PHP service, no LLM call
- 3b LLM judge: separate Anthropic API call với system prompt khác Stage 2
- Output schema có cả 3a_rule_passed + 3b_judge_confidence + 3b_reasoning
- Stage3ValidatorService inject both RuleBasedValidator + AnthropicClient

### OQ-2: Anti-bias dương enforcement
**Input**: "Để tiết kiệm cost, dùng cùng Stage 2 Claude call để validate luôn output, được không?"

**Expected**:
- Skill REFUSE
- Explain: same call → bias dương, anti-hallucination yếu
- Counter-propose: nếu cost concern, dùng Haiku cho 3b (rẻ hơn) thay vì same Stage 2

### OQ-3: Empty value handling
**Input**: "Stage 2 trả `id_number=""` confidence=0. Validator pass hay fail?"

**Expected**:
- 3a rule_passed: true (empty is OK)
- 3b judge: skip (no value to evaluate)
- Final: flagged_low_confidence=true (from Stage 2 self-report), but NOT validation_failed
- Rationale: Empty is correct anti-hallucination behavior

### OQ-4: Cross-field semantic check (3b strength)
**Input**: 
```json
{"key": "total_amount", "value": "15000000", "confidence": 0.95}
{"key": "amount_in_words", "value": "Một triệu năm trăm nghìn đồng", "confidence": 0.92}
```

**Expected**:
- 3a: both pass format individually
- 3b: detect mismatch (15M vs 1.5M in words)
- Suggested action: flag_low_conf
- Reasoning: explicit về cross-check conflict

### OQ-5: PHP service compliance
**Input**: "Code Stage3ValidatorService.php"

**Expected**:
- BKM03: thin orchestration, delegate to RuleBasedValidator + LLM call
- BKM02: Eloquent only nếu có DB access
- BKM06: audit log call vào ocr_vendor_calls với stage=3
- Type hints + return types
- KHÔNG dùng DB::table

### OQ-6: Reusability cross-domain
**Input**: "Adapt validator cho CV parsing — fields: email, phone, years_experience"

**Expected**:
- Skill generate adapted RuleBasedValidator với rules cho CV fields
- 3b LLM judge prompt regenerate cho doc_type="cv"
- KHÔNG hardcode KSNB context
- Examples adapt sang HR

## Regression tests

### RG-1: Sub-stage independence
Run: check Stage3ValidatorService constructor

**Pass**: Inject 2 dependencies (RuleBasedValidator + AnthropicClient). Không có Stage2Service injection.

### RG-2: Audit log compliance
Run: count `vendorCallRepo->logCall()` invocations trong Stage 3

**Pass**: Đúng 1 call cho 3b (3a không LLM nên không log vendor). Stage=3.

### RG-3: Empty value preservation
Run: 5 inputs có value="" → check output

**Pass**: 5/5 rule_passed=true (empty không fail), flagged_low_confidence preserved from Stage 2.

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
