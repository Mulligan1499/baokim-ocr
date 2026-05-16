---
name: ocr-confidence-aggregator
description: |
  Skill thiết kế Stage 5 Confidence Aggregator trong harness 7 stages của OCR Baokim KSNB. Aggregate multi-signal confidence: Stage 2 self-report (LLM trả về) + Stage 3a rule-based + Stage 3b LLM-judge → final per-field confidence + overall document confidence + quality bucket (high/medium/low) + requires_review flag. Weighted formula: self_report×0.3 + rule×0.4 + judge×0.3. Critical fields weight 2× trong overall confidence. Threshold: ≥0.85=high, 0.5-0.85=medium, <0.5=low (requires_review). Đầu vào: validation_results từ Stage 3. Đầu ra: aggregated_key_values + overall_confidence + quality + requires_review + warnings + PHP service ConfidenceAggregator. Generic enough cho mọi multi-signal scoring use case (fraud detection, search ranking, recommendation).
  Triggers: "Stage 5 aggregator", "multi-signal confidence aggregation", "weighted confidence formula", "quality bucket high/medium/low", "requires_review threshold", "design Stage5ConfidenceAggregator", "aggregate confidence từ multiple signals", "overall document confidence calculation".
  Anti-triggers: single-signal confidence (chỉ self-report) — không cần aggregator, ML model confidence calibration (Platt scaling, isotonic — đó là ML topic), business rule scoring không phải confidence (vd: customer credit score).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: dev
created: 2026-05-15
updated: 2026-05-15
tags: [dev, ocr, confidence, aggregation, multi-signal, stage-5, baokim]
---

# OCR Confidence Aggregator (Stage 5)

## Mục đích

Stage 5 trong harness 7 stages (file 02 Section 4, Section 10):
- Aggregate 3 signals: Stage 2 self-report + Stage 3a rule + Stage 3b LLM-judge
- Output: per-field final confidence + overall document confidence + quality bucket + requires_review flag
- Quyết định: KSNB có cần review manual không (UX critical)

**Reference**: file 02 Section 10 (Confidence strategy chi tiết)

## Khi nào dùng

✅ Build Stage 5 cho project OCR Baokim
✅ Design multi-signal confidence cho LLM extraction pipeline khác (CV, contract)
✅ Adapt confidence aggregation cho non-OCR pipelines: fraud detection, search ranking
✅ Code `Stage5ConfidenceAggregatorService` Laravel

❌ Single-signal confidence (chỉ LLM self-report) — không cần aggregator
❌ ML model calibration (Platt scaling, isotonic regression) — ML topic
❌ Business scoring (credit score, lead score) — domain rules khác

## Workflow

### Step 1: Identify signal sources

Multi-signal cho OCR Stage 5:

| Signal | Source | Weight | Range | Notes |
|---|---|---|---|---|
| Self-report | Stage 2 LLM | 0.3 | [0, 1] | LLM tự đánh giá khi extract |
| Rule-based | Stage 3a | 0.4 | {0, 1} discrete | Pass/fail format regex |
| LLM-judge | Stage 3b | 0.3 | [0, 1] | Separate LLM evaluate plausibility |

Weights chọn 0.4 cho rule vì rule là hard evidence (format đúng/sai có ground truth). Self + judge là soft signals.

### Step 2: Apply aggregation formula

Per-field:
```
final_confidence = (self × 0.3) + (rule × 0.4) + (judge × 0.3)
```

Exception (forced low):
```
IF rule_passed == false AND field_is_critical:
    final_confidence = min(final_confidence, 0.4)
```

Rationale: format fail trên critical field (CCCD sai format) là hard error → cap low bất kể self + judge cao.

Overall document confidence:
```
overall = SUM(field_confidence × field_weight) / SUM(field_weight)
where field_weight = 2 (critical) or 1 (normal)
```

### Step 3: Determine quality bucket + requires_review

Threshold per file 02 Section 10:

| Bucket | Range | requires_review | Warning code |
|---|---|---|---|
| high | ≥ 0.85 | false | none |
| medium | 0.5 - 0.85 | true | MEDIUM_CONFIDENCE |
| low | < 0.5 | true | LOW_CONFIDENCE |

Additional warnings:
- `LOW_IMAGE_QUALITY` nếu Stage 1 trả image_quality_note có "blurry"/"obscured"
- `CRITICAL_FIELD_FAILED` nếu có ≥1 critical field rule_passed=false
- `MULTI_SIGNAL_DISAGREE` nếu self vs judge gap > 0.5

### Step 4: Output structure

```json
{
  "aggregated_key_values": [
    {
      "key": "id_number",
      "value": "001234567890",
      "value_translated_vi": null,
      "final_confidence": 0.94,
      "signals": {
        "self_report": 0.97,
        "rule_based": 1.0,
        "llm_judge": 0.95
      },
      "critical": true,
      "flagged": false,
      "warnings": []
    }
  ],
  "overall_confidence": 0.92,
  "quality": "high",
  "requires_review": false,
  "warnings": [],
  "review_priority_fields": []  // fields có confidence thấp nhất, sorted
}
```

## PHP Service skeleton

```php
<?php
namespace App\Services\Ocr;

class Stage5ConfidenceAggregatorService
{
    private const WEIGHT_SELF = 0.3;
    private const WEIGHT_RULE = 0.4;
    private const WEIGHT_JUDGE = 0.3;
    
    private const THRESHOLD_HIGH = 0.85;
    private const THRESHOLD_MEDIUM = 0.5;
    
    private const CRITICAL_WEIGHT = 2;
    private const NORMAL_WEIGHT = 1;
    
    private const RULE_FAIL_CAP = 0.4;

    public function aggregate(array $validationResults, array $criticalFieldKeys, ?string $imageQualityNote = null): array
    {
        $aggregatedFields = [];
        $weightedSum = 0;
        $totalWeight = 0;
        $criticalFailures = [];
        $signalDisagreements = [];

        foreach ($validationResults['validation_results'] as $field) {
            $isCritical = in_array($field['key'], $criticalFieldKeys);
            
            $finalConf = $this->computeFieldConfidence($field, $isCritical, $criticalFailures);
            
            $weight = $isCritical ? self::CRITICAL_WEIGHT : self::NORMAL_WEIGHT;
            $weightedSum += $finalConf * $weight;
            $totalWeight += $weight;
            
            // Check signal disagreement
            if (abs($field['stage2_self_confidence'] - $field['stage3b_judge_confidence']) > 0.5) {
                $signalDisagreements[] = $field['key'];
            }
            
            $aggregatedFields[] = [
                'key' => $field['key'],
                'value' => $field['value'],
                'final_confidence' => round($finalConf, 3),
                'signals' => [
                    'self_report' => $field['stage2_self_confidence'],
                    'rule_based' => $field['stage3a_rule_passed'] ? 1.0 : 0.0,
                    'llm_judge' => $field['stage3b_judge_confidence'],
                ],
                'critical' => $isCritical,
                'flagged' => $finalConf < self::THRESHOLD_MEDIUM,
                'warnings' => $this->buildFieldWarnings($field, $isCritical),
            ];
        }

        $overall = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
        
        return [
            'aggregated_key_values' => $aggregatedFields,
            'overall_confidence' => round($overall, 3),
            'quality' => $this->bucketize($overall),
            'requires_review' => $overall < self::THRESHOLD_HIGH,
            'warnings' => $this->buildOverallWarnings($overall, $criticalFailures, $signalDisagreements, $imageQualityNote),
            'review_priority_fields' => $this->sortByConfidence($aggregatedFields),
        ];
    }

    private function computeFieldConfidence(array $field, bool $isCritical, array &$criticalFailures): float
    {
        $self = $field['stage2_self_confidence'] ?? 0;
        $rule = $field['stage3a_rule_passed'] ? 1.0 : 0.0;
        $judge = $field['stage3b_judge_confidence'] ?? 0;
        
        $final = ($self * self::WEIGHT_SELF) + ($rule * self::WEIGHT_RULE) + ($judge * self::WEIGHT_JUDGE);
        
        // Critical field rule failure → cap low
        if ($isCritical && !$field['stage3a_rule_passed']) {
            $criticalFailures[] = $field['key'];
            $final = min($final, self::RULE_FAIL_CAP);
        }
        
        return $final;
    }

    private function bucketize(float $confidence): string
    {
        return match (true) {
            $confidence >= self::THRESHOLD_HIGH => 'high',
            $confidence >= self::THRESHOLD_MEDIUM => 'medium',
            default => 'low',
        };
    }

    private function buildOverallWarnings(float $overall, array $criticalFailures, array $disagreements, ?string $imageQualityNote): array
    {
        $warnings = [];
        if ($overall < self::THRESHOLD_MEDIUM) {
            $warnings[] = 'LOW_CONFIDENCE';
        } elseif ($overall < self::THRESHOLD_HIGH) {
            $warnings[] = 'MEDIUM_CONFIDENCE';
        }
        if (!empty($criticalFailures)) {
            $warnings[] = 'CRITICAL_FIELD_FAILED';
        }
        if (!empty($disagreements)) {
            $warnings[] = 'MULTI_SIGNAL_DISAGREE';
        }
        if ($imageQualityNote && (str_contains(strtolower($imageQualityNote), 'blurry') || str_contains(strtolower($imageQualityNote), 'obscured'))) {
            $warnings[] = 'LOW_IMAGE_QUALITY';
        }
        return $warnings;
    }

    private function sortByConfidence(array $fields): array
    {
        $sorted = array_filter($fields, fn($f) => $f['flagged']);
        usort($sorted, fn($a, $b) => $a['final_confidence'] <=> $b['final_confidence']);
        return array_column($sorted, 'key');
    }

    private function buildFieldWarnings(array $field, bool $isCritical): array
    {
        $warnings = [];
        if ($isCritical && !$field['stage3a_rule_passed']) {
            $warnings[] = 'CRITICAL_RULE_FAILED';
        }
        return $warnings;
    }
}
```

## Examples

### Example 1: High confidence happy path

**Input**:
- CCCD id_number: self=0.97, rule=pass, judge=0.95, critical=true
- full_name: self=0.95, rule=pass, judge=0.95, critical=true

**Aggregation**:
- id_number: (0.97×0.3) + (1.0×0.4) + (0.95×0.3) = 0.971
- full_name: (0.95×0.3) + (1.0×0.4) + (0.95×0.3) = 0.97

**Overall** (cả 2 critical, weight 2):
- (0.971 × 2 + 0.97 × 2) / (2+2) = 0.97 → **high**, requires_review=false

### Example 2: Critical field rule fail (forced low)

**Input**:
- id_number: self=0.85, rule=fail (chỉ 11 digits), judge=0.4, critical=true
- full_name: self=0.95, rule=pass, judge=0.95, critical=true

**Aggregation**:
- id_number raw: (0.85×0.3) + (0×0.4) + (0.4×0.3) = 0.375
- id_number FORCED CAP: 0.375 (đã dưới 0.4 nên không cap thêm)
- full_name: 0.97

**Overall**:
- (0.375 × 2 + 0.97 × 2) / 4 = 0.67 → **medium**, requires_review=true
- Warnings: MEDIUM_CONFIDENCE, CRITICAL_FIELD_FAILED

### Example 3: Signal disagreement

**Input**:
- date_of_birth: self=0.95, rule=pass (valid date), judge=0.4 ("date in future, suspicious")

**Aggregation**:
- raw: (0.95×0.3) + (1.0×0.4) + (0.4×0.3) = 0.805 → medium-high
- Signal gap: |0.95 - 0.4| = 0.55 > 0.5 → MULTI_SIGNAL_DISAGREE warning

**Output**: confidence 0.805, flagged=false (cao hơn 0.5), nhưng warnings có disagreement → KSNB nên review.

### Example 4: Reusability — fraud detection scoring

**Adapt cho fraud detection** (cùng skill, đổi signal sources):
- Signal 1: ML model probability (0.7) — weight 0.5
- Signal 2: Rule-based heuristic count (0.6) — weight 0.3
- Signal 3: Customer history risk score (0.4) — weight 0.2

**Aggregation**:
- final = (0.7×0.5) + (0.6×0.3) + (0.4×0.2) = 0.61

**Bucket** (custom thresholds cho fraud):
- ≥ 0.8: high fraud risk → block
- 0.5-0.8: medium → manual review
- < 0.5: low → allow

→ Cùng skill structure, đổi signals + weights + thresholds.

## What NOT to do

❌ KHÔNG hardcode weights 0.3/0.4/0.3 vào code không thể config — dùng constants có thể override
❌ KHÔNG skip forced cap rule (critical + rule_fail → cap 0.4) — quan trọng cho safety
❌ KHÔNG aggregate khi 1 trong 3 signal missing — treat missing = 0, KHÔNG = mean others
❌ KHÔNG round final confidence trước khi check threshold — round chỉ ở display
❌ KHÔNG dùng max() thay vì weighted avg — sẽ over-confident
❌ KHÔNG ignore warning `MULTI_SIGNAL_DISAGREE` — flag cho KSNB review

## Reusability angle

Generic multi-signal aggregation pattern, dùng cho:
- OCR pipeline (current)
- Fraud detection (ML + rule + history)
- Search ranking (relevance + freshness + popularity)
- Recommendation systems (CF + content-based + popularity)
- Customer credit scoring (income + history + behavior)
- Document quality scoring (image + text + metadata)

Skill này là pattern foundation cho weighted aggregation + bucketization.

## Reference foundation

- file 02 Section 10: confidence strategy chi tiết
- `dev-baokim-laravel-repo-pattern`: PHP service convention
- `ocr-extraction-validator`: provides Stage 3 input

## Maintenance & Roadmap

- v0.2.0: thresholds config qua Laravel config (`config/ocr.php`) thay vì class constants
- v0.3.0: adaptive weights theo doc_type (vd: passport rule weight cao hơn id_card vì regex strict hơn)
- v1.0.0 (active): tested 1000+ extractions, calibration error < 5%

Tests: `tests/prompts.md`
