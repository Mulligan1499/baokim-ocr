---
name: ocr-confidence-aggregator
description: Thiết kế Stage 5 Confidence Aggregator — tổng hợp tín hiệu từ Stage 2 + 3a + 3b thành overall confidence + quality bucket + requires_review flag. Pattern multi-signal scoring dùng được cho OCR pipeline, fraud detection, search ranking, recommendation.
when_to_use: |
  Triggers: "Stage 5 aggregator", "multi-signal confidence", "weighted confidence per field",
  "quality bucket high/medium/low", "requires_review threshold", "design Stage5ConfidenceAggregator",
  "aggregate confidence từ multiple signals", "overall document confidence", "review_priority sort
  fields by confidence".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Single-signal confidence (chỉ self-report LLM) — không cần aggregator
  - ML model confidence calibration (Platt scaling, isotonic regression) — đó là ML topic riêng
  - Business rule scoring không phải confidence (credit score, lead score)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: dev
created: 2026-05-15
updated: 2026-05-25
tags: [dev, ocr, confidence, aggregation, multi-signal, stage-5, baokim]
---

# OCR Confidence Aggregator (Stage 5)

## Mục đích

Stage 5 trong harness 7 stages (file 02 Section 4, Section 10):
- Nhận 3 tín hiệu per field: Stage 2 self-report + Stage 3a rule_passed + Stage 3b judge_confidence
- Output: per-field final confidence + overall document confidence + quality bucket + requires_review flag + warnings + review_priority_fields
- Quyết định: KSNB có cần review manual không (UX critical)

**Reference**: file 02 Section 10 (Confidence strategy chi tiết), AC R3 + AC-AI-01.

## Khi nào dùng

✅ Build Stage 5 cho project OCR Baokim
✅ Design multi-signal confidence cho LLM extraction pipeline khác (CV parsing, contract extraction)
✅ Adapt pattern cho non-OCR scoring: fraud detection, search ranking, recommendation
✅ Code `Stage5ConfidenceAggregator` Laravel service

❌ Single-signal confidence (chỉ LLM self-report) — không cần aggregator
❌ ML model calibration (Platt scaling, isotonic regression) — ML topic
❌ Business scoring (credit score, lead score) — domain rules khác

## Confidence Policy (per AC R3 + AC-AI-01)

> **Quan trọng**: KHÔNG blend 3 signals vào exposed confidence. Pattern Baokim chọn separation of concerns.

```
Per-field exposed confidence:
    exposed = self_report (Stage 2)

Critical + rule_fail cap policy:
    IF is_critical AND rule_passed == false:
        exposed = min(self_report, 0.4)

Overall document confidence (weighted avg):
    overall = SUM(exposed × field_weight) / SUM(field_weight)
    where field_weight = 2 (critical) or 1 (normal)
```

**Tại sao không blend** (decision rationale):

| Signal | Vai trò | Lý do KHÔNG blend |
|---|---|---|
| **Self-report** (Stage 2) | **Exposed confidence chính** | Là số được calibrated bởi LLM **trên ảnh thật** — ground truth gần nhất |
| **Rule_passed** (Stage 3a) | **Cap signal** + warning | Hard evidence (format đúng/sai). Dùng cap critical+fail → 0.4 (force flag). Không mix vào số → giữ self_report transparent |
| **Judge_confidence** (Stage 3b) | **Disagreement signal** + warning | LLM #2 evaluate plausibility (không xem ảnh). Dùng detect bias (\|self − judge\| > 0.5 → warn). Không mix vì model #2 không có visual context → blend sẽ dilute |

Pattern này tránh **false precision**: nếu blend `(0.85×0.3 + 1.0×0.4 + 0.4×0.3) = 0.775`, dev sẽ cố lập luận tại sao 0.775 không phải 0.78. Số `self_report = 0.85` calibrated rõ ràng từ LLM xem ảnh, dùng nguyên là honest.

## Workflow

### Step 1: Identify signal sources

| Signal | Source | Type | Vai trò trong Stage 5 |
|---|---|---|---|
| Self-report | Stage 2 LLM | `[0, 1]` float | **Exposed confidence** |
| Rule_passed | Stage 3a regex | `bool` | Cap signal (critical+fail → cap 0.4) |
| Judge_confidence | Stage 3b LLM (no image) | `[0, 1]` float | Disagreement detection |

### Step 2: Per-field aggregation

```python
def compute_field_confidence(field, is_critical):
    self_report = field.stage2_self_confidence
    rule_passed = field.stage3a_rule_passed
    judge_conf  = field.stage3b_judge_confidence

    # Exposed = self_report (default)
    exposed = self_report

    # Critical + rule fail → cap force flag (R4 < 0.5)
    if is_critical and not rule_passed:
        exposed = min(self_report, 0.4)

    # Disagreement detection (warning, not blend)
    disagreement = abs(self_report - judge_conf) > 0.5

    return {
        'exposed_confidence': exposed,
        'signals': {
            'self_report': self_report,
            'rule_passed': rule_passed,
            'judge_confidence': judge_conf,
        },
        'flagged': exposed < 0.5,  # below MEDIUM threshold
        'disagreement_warn': disagreement,
    }
```

### Step 3: Overall document confidence (weighted average)

```
overall = SUM(field_exposed × field_weight) / SUM(field_weight)
```

Với `field_weight`:
- Critical field → `weight = 2`
- Normal field → `weight = 1`

Per AC R3: *"overall_confidence = weighted average của các field confidence (critical 2×, normal 1×)"*.

### Step 4: Quality bucket + requires_review

| Bucket | Range | requires_review |
|---|---|---|
| high | ≥ 0.85 | false |
| medium | 0.5 - 0.85 | **true** (< HIGH threshold) |
| low | < 0.5 | true |

Lưu ý: `requires_review = overall < 0.85` (không phải `< 0.5`). Logic: bất kỳ doc nào không đạt `high` → KSNB phải rà soát.

### Step 5: Generate warnings (multi-level)

**Per-field warnings** (gắn vào field object):
- `CRITICAL_RULE_FAILED` — critical field + rule_passed=false
- `SELF_VS_JUDGE_DISAGREE` — |self − judge| > 0.5
- `JUDGE_FLAGGED` — Stage 3b judge_action = 'flag_low_conf'

**Document-level warnings** (gắn `field=_global`):
- `LOW_CONFIDENCE` — overall < 0.5
- `MEDIUM_CONFIDENCE` — overall 0.5..0.85
- `CRITICAL_FIELDS_FAILED` — ≥1 critical field rule fail
- `MULTI_SIGNAL_DISAGREE` — ≥1 field có disagreement
- `LOW_IMAGE_QUALITY` — Stage 1 image_quality_note chứa keyword (blurry/obscured/mờ/nghiêng/che/nhăn)

### Step 6: Review priority sort

List field keys sorted **ascending** by exposed confidence (lowest first), chỉ include flagged hoặc < HIGH threshold. KSNB rà soát từ trên xuống.

### Step 7: Output structure

```json
{
  "aggregated": {
    "so_cccd": {
      "value": "001234567890",
      "final_confidence": 0.97,
      "signals": {
        "self_report": 0.97,
        "rule_passed": true,
        "judge_confidence": 0.95
      },
      "critical": true,
      "flagged": false,
      "warnings": []
    }
  },
  "key_values_raw_map": { "so_cccd": "001234567890" },
  "confidence_per_field": { "so_cccd": 0.97 },
  "overall_confidence": 0.92,
  "quality": "high",
  "requires_review": false,
  "warnings": [],
  "review_priority_fields": []
}
```

## PHP Service skeleton (align Stage5ConfidenceAggregator.php)

```php
<?php
namespace App\Services\Ocr;

class Stage5ConfidenceAggregator
{
    private const RULE_FAIL_CAP_CRITICAL = 0.4;

    private const WEIGHT_FIELD_CRITICAL = 2;
    private const WEIGHT_FIELD_NORMAL = 1;

    public function __construct(
        private float $thresholdHigh = 0.85,
        private float $thresholdMedMin = 0.50,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            thresholdHigh: (float) config('ocr.confidence_thresholds.high', 0.85),
            thresholdMedMin: (float) config('ocr.confidence_thresholds.medium_min', 0.50),
        );
    }

    public function aggregate(
        array $keyValuesRaw,
        array $stage2SelfConf,
        array $stage3PerField,
        array $criticalFieldKeys,
        ?string $imageQualityNote = null,
    ): array {
        $aggregated = [];
        $weightedSum = 0.0;
        $totalWeight = 0;
        $criticalFailures = [];
        $signalDisagreements = [];

        foreach ($keyValuesRaw as $key => $value) {
            $isCritical = in_array($key, $criticalFieldKeys, true);
            $self = (float) ($stage2SelfConf[$key] ?? 0);
            $entry = $stage3PerField[$key] ?? null;
            $rule = $entry ? (bool) $entry['rule_passed'] : true;
            $judge = $entry ? (float) $entry['judge_confidence'] : 0.5;

            // EXPOSED = self_report (per AC R3)
            $exposed = $self;

            // CAP: critical + rule fail → min(self, 0.4)
            if ($isCritical && !$rule) {
                $criticalFailures[] = $key;
                $exposed = min($self, self::RULE_FAIL_CAP_CRITICAL);
            }

            // Disagreement signal (warning only, NOT blend)
            if (abs($self - $judge) > 0.5) {
                $signalDisagreements[] = $key;
            }

            $fieldWeight = $isCritical
                ? self::WEIGHT_FIELD_CRITICAL
                : self::WEIGHT_FIELD_NORMAL;
            $weightedSum += $exposed * $fieldWeight;
            $totalWeight += $fieldWeight;

            $aggregated[$key] = [
                'value' => $value,
                'final_confidence' => round($exposed, 3),
                'signals' => [
                    'self_report' => round($self, 3),
                    'rule_passed' => $rule,
                    'judge_confidence' => round($judge, 3),
                ],
                'critical' => $isCritical,
                'flagged' => $exposed < $this->thresholdMedMin,
                'warnings' => $this->buildFieldWarnings($isCritical, $rule, $self, $judge, $entry),
            ];
        }

        $overall = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        return [
            'aggregated' => $aggregated,
            'overall_confidence' => round($overall, 3),
            'quality' => $this->bucketize($overall),
            'requires_review' => $overall < $this->thresholdHigh,
            'warnings' => $this->buildAllWarnings($overall, $criticalFailures, $signalDisagreements, $imageQualityNote),
            'review_priority_fields' => $this->sortByConfidence($aggregated),
        ];
    }

    private function bucketize(float $confidence): string
    {
        return match (true) {
            $confidence >= $this->thresholdHigh => 'high',
            $confidence >= $this->thresholdMedMin => 'medium',
            default => 'low',
        };
    }

    // ... buildFieldWarnings(), buildAllWarnings(), sortByConfidence() implementations
}
```

→ Reference impl thật: `app/Services/Ocr/Stage5ConfidenceAggregator.php`.

## Examples

### Example 1: Happy path (all signals high, no cap)

**Input** — 2 critical fields CCCD:

| Field | self | rule | judge | critical |
|---|---|---|---|---|
| so_cccd | 0.97 | pass | 0.95 | ✓ |
| ho_ten | 0.95 | pass | 0.95 | ✓ |

**Per-field exposed**:
- `so_cccd`: exposed = self = **0.97** (no cap)
- `ho_ten`: exposed = self = **0.95** (no cap)

**Overall**:
```
weighted_sum = 0.97×2 + 0.95×2 = 3.84
total_weight = 2 + 2 = 4
overall = 3.84 / 4 = 0.96
```

**Bucket**: high (0.96 ≥ 0.85) → `requires_review = false`. Warnings: empty.

### Example 2: Critical + rule fail (force cap)

**Input** — critical field id_number sai format (chỉ 11 chữ số):

| Field | self | rule | judge | critical |
|---|---|---|---|---|
| so_cccd | 0.85 | **fail** | 0.4 | ✓ |
| ho_ten | 0.95 | pass | 0.95 | ✓ |

**Per-field exposed**:
- `so_cccd`: critical + rule fail → exposed = `min(0.85, 0.4)` = **0.4**
- `ho_ten`: exposed = self = **0.95**

**Overall**:
```
weighted_sum = 0.4×2 + 0.95×2 = 2.7
total_weight = 4
overall = 0.675
```

**Bucket**: medium (0.5 ≤ 0.675 < 0.85) → `requires_review = true`.

**Warnings**:
- Field-level `so_cccd`: `CRITICAL_RULE_FAILED`
- Document-level: `MEDIUM_CONFIDENCE`, `CRITICAL_FIELDS_FAILED`

### Example 3: Signal disagreement (warning, không blend)

**Input** — ngày sinh self cao nhưng judge nghi ngờ:

| Field | self | rule | judge | critical |
|---|---|---|---|---|
| ngay_sinh | 0.95 | pass | 0.40 | ✓ |

**Per-field exposed**:
- `ngay_sinh`: exposed = self = **0.95** (no cap, rule passed)
- Disagreement: |0.95 − 0.40| = 0.55 > 0.5 → **warn SELF_VS_JUDGE_DISAGREE**

**Quan trọng**: exposed vẫn = 0.95, KHÔNG bị dilute xuống bởi judge thấp. Disagreement chỉ là **warning signal cho KSNB review thủ công**.

**Output cho field này**:
```json
{
  "value": "01/01/2050",
  "final_confidence": 0.95,
  "signals": { "self_report": 0.95, "rule_passed": true, "judge_confidence": 0.40 },
  "flagged": false,
  "warnings": [{ "code": "SELF_VS_JUDGE_DISAGREE", "msg": "Hai bước AI chấm điểm khác nhau: 0.95 vs 0.40" }]
}
```

→ KSNB nhìn cờ warning → đối chiếu file gốc → quyết định.

### Example 4: Reusability — fraud detection scoring

Adapt cho fraud detection (cùng pattern "primary signal + guardrails"):

**Setup**:
- Primary signal: ML fraud probability score `[0, 1]` (Stage 2 equivalent)
- Cap signal: deterministic blocklist match `{0, 1}` (Stage 3a equivalent — IF blocklist match AND high-value transaction → cap exposed ≥ 0.9 = force block)
- Disagreement signal: rule-based heuristic count diverges → warn

**Logic**:
```python
exposed = ml_fraud_prob

if blocklist_matched and tx_amount > 10000:
    exposed = max(ml_fraud_prob, 0.9)  # force block

if abs(ml_fraud_prob - heuristic_score) > 0.5:
    warnings.append('FRAUD_SIGNAL_DISAGREE')
```

**Bucket** (custom thresholds):
- exposed ≥ 0.9 → block
- 0.5 - 0.9 → manual review
- < 0.5 → allow

→ Cùng pattern: primary signal exposed, guardrails cap, disagreement warn. Generic cho mọi multi-signal scoring khi 1 signal là calibrated primary.

## What NOT to do

❌ KHÔNG blend 3 signals vào exposed_confidence — pattern Baokim chọn separation
❌ KHÔNG dùng max() thay weighted avg cho overall — sẽ over-confident
❌ KHÔNG skip cap critical+rule_fail — quan trọng cho safety AC R4 < 0.5 trigger flag
❌ KHÔNG bỏ disagreement warning — đây là gateway cho KSNB review
❌ KHÔNG ignore Stage 1 image_quality_note — warning LOW_IMAGE_QUALITY triggered từ đây
❌ KHÔNG hardcode threshold 0.85/0.5 — config qua `config/ocr.php` để A/B test

## Reusability angle

Generic pattern "primary signal + guardrails + warning detection":
- OCR pipeline (current)
- Fraud detection (ML prob + blocklist cap + heuristic disagreement)
- Search ranking (relevance + business rule boost + click-through divergence)
- Recommendation (CF + cold-start fallback + popularity divergence)
- Document quality scoring (visual + text + metadata signals)

Bài học: khi có 1 signal calibrated trên ground truth gần nhất (LLM xem ảnh, ML on labeled data) → dùng nó làm exposed primary. Signals khác dùng làm guardrail (cap) hoặc detector (warning), không blend.

## Reference foundation

- AC R3 + AC-AI-01 — formal confidence contract
- file 02 Section 10 — confidence strategy đầy đủ
- `dev-baokim-laravel-repo-pattern` — PHP service convention
- `ocr-extraction-validator` — provides Stage 3 input

## Maintenance & Roadmap

- v0.2.0 (current — 2026-05-25): aligned với code production `Stage5ConfidenceAggregator.php`. Confidence policy = exposed = self_report + cap critical+rule_fail. Fixed misleading "blend 0.3+0.4+0.3" của v0.1.0.
- v0.3.0: adaptive threshold theo doc_type (vd: passport threshold cao hơn invoice vì format strict hơn)
- v1.0.0: tested 1000+ extractions, calibration error < 5%

Tests: `tests/prompts.md`
