# Test prompts: ocr-confidence-aggregator

## Trigger tests (skill SHOULD activate)

1. "Design Stage 5 Confidence Aggregator OCR"
2. "Weighted formula confidence từ 3 signals"
3. "Aggregate confidence Stage 2 + Stage 3a + Stage 3b"
4. "Build Stage5ConfidenceAggregatorService Laravel"
5. "Quality bucket high/medium/low cho OCR result"
6. "requires_review threshold logic"
7. "Multi-signal scoring pattern Baokim"
8. "Confidence aggregator cho fraud detection (cross-domain)"

## Anti-trigger tests (skill should NOT activate)

1. "Single LLM confidence self-report là đủ" — không cần aggregator
2. "Platt scaling calibration cho ML model" — ML topic, không phải skill này
3. "Customer credit score business rule" — domain rules khác
4. "Validate extraction output có hợp lý không" — Stage 3 dùng `ocr-extraction-validator`
5. "Schema validation JSON" — không liên quan confidence

## Output quality tests

### OQ-1: Formula correctness
**Input**: 
```
self=0.9, rule=1.0, judge=0.8, critical=true
```

**Expected**: final = 0.9×0.3 + 1.0×0.4 + 0.8×0.3 = 0.91

Skill output PHP code phải compute đúng số này.

### OQ-2: Forced cap on critical fail
**Input**:
```
self=0.9, rule=fail (0), judge=0.7, critical=true
```

**Expected**: 
- raw_final = 0.9×0.3 + 0×0.4 + 0.7×0.3 = 0.48
- After cap: min(0.48, 0.4) = 0.4
- Warning: CRITICAL_FIELD_FAILED

### OQ-3: Overall weighted average
**Input**: 3 critical fields conf [0.9, 0.85, 0.95] + 2 normal fields conf [0.7, 0.8]

**Expected**:
- weighted_sum = 0.9×2 + 0.85×2 + 0.95×2 + 0.7×1 + 0.8×1 = 7.0
- total_weight = 2+2+2+1+1 = 8
- overall = 7.0 / 8 = 0.875 → high

### OQ-4: Signal disagreement detection
**Input**:
```
self=0.95, judge=0.4, gap=0.55
```

**Expected**:
- Final confidence raw OK (0.6+)
- Warning MULTI_SIGNAL_DISAGREE added
- review_priority_fields có field này

### OQ-5: Reusability — non-OCR domain
**Input**: "Adapt aggregator cho fraud detection: ML prob (weight 0.5) + rule heuristic (0.3) + history score (0.2)"

**Expected**:
- Skill regenerate formula với new weights
- Bucket thresholds custom cho fraud (ví dụ: >0.8 high risk)
- Same PHP service structure, đổi config

### OQ-6: PHP service compliance
**Input**: "Code Stage5ConfidenceAggregatorService.php"

**Expected**:
- Pure computation service, KHÔNG có DB call (không cần Repository)
- Constants for weights + thresholds (có thể override v0.2.0)
- Type hints float for confidence values
- Pure function: input array → output array, no side effect

## Regression tests

### RG-1: Formula consistency
Run same input 100 lần → check output identical

**Pass**: 100% same output (deterministic).

### RG-2: Bucket boundary
Run với confidence = 0.85 exactly → bucket=?

**Pass**: high (>= threshold, không phải strict >). Edge case 0.5 → medium.

### RG-3: Empty signals handling
Run với missing 1 signal (judge=null) → output?

**Pass**: Skill TREAT missing = 0 (conservative), KHÔNG = average of other signals (vì bias).

### RG-4: Performance
Run aggregate trên 50 fields × 1000 documents

**Pass**: < 1 sec total (pure computation, no I/O).

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
