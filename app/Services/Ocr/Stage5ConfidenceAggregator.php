<?php

namespace App\Services\Ocr;

/**
 * Stage 5 — Confidence aggregator (per AC R3 + AC-AI-01).
 *
 * Inputs (per field): self_report (Stage 2), rule_passed (Stage 3a), judge_confidence (Stage 3b).
 * Output: aggregated_key_values[] + overall_confidence + quality bucket + requires_review + warnings.
 *
 * Per AC R3 — `overall_confidence = weighted average của các field confidence (critical 2×, normal 1×)`.
 * Per AC-AI-01 — per-field confidence là số thật, overall = weighted avg những số đó.
 *
 * Confidence policy:
 *  - Exposed confidence = self_report (Stage 2)
 *  - IF critical AND rule_passed=false → cap exposed confidence at 0.4 (R4 < 0.5 → flagged)
 *  - Stage 3b judge + Stage 3a rule dùng làm signal cho warning + review priority, KHÔNG mix vào số confidence
 *
 * Buckets: high ≥ 0.85 | medium 0.5..0.85 | low < 0.5 (requires_review when < 0.85).
 */
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

    /**
     * @param  array<string,string>             $keyValuesRaw      key => raw value
     * @param  array<string,float>              $stage2SelfConf    key => self-report
     * @param  array<string,array>              $stage3PerField    key => {rule_passed,rule_reason,judge_confidence,judge_reason,judge_action}
     * @param  string[]                         $criticalFieldKeys list of keys deemed critical
     * @param  ?string                          $imageQualityNote  Stage 1 image_quality_note (free text)
     * @return array
     */
    public function aggregate(
        array $keyValuesRaw,
        array $stage2SelfConf,
        array $stage3PerField,
        array $criticalFieldKeys,
        ?string $imageQualityNote = null,
    ): array {
        $thresholdHigh = $this->thresholdHigh;
        $thresholdMedMin = $this->thresholdMedMin;

        $aggregated = [];
        $confidencePerField = [];
        $weightedSum = 0.0;
        $totalWeight = 0;
        $criticalFailures = [];
        $signalDisagreements = [];
        $warningsFields = [];

        foreach ($keyValuesRaw as $key => $value) {
            $isCritical = in_array($key, $criticalFieldKeys, true);
            $self = (float) ($stage2SelfConf[$key] ?? 0);
            $entry = $stage3PerField[$key] ?? null;
            $rule = $entry ? (bool) $entry['rule_passed'] : true;
            $judge = $entry ? (float) $entry['judge_confidence'] : 0.5;

            // AC R3/AC-AI-01: confidence exposed = self_report (Stage 2). Stage 3 chỉ dùng làm signal.
            $exposedConfidence = $self;

            // Critical + rule fail → cap confidence < 0.5 để trigger R4 flag (KSNB phải review)
            if ($isCritical && ! $rule) {
                $criticalFailures[] = $key;
                $exposedConfidence = min($self, self::RULE_FAIL_CAP_CRITICAL);
            }

            // Signal disagreement: |self - judge| > 0.5
            if (abs($self - $judge) > 0.5) {
                $signalDisagreements[] = $key;
            }

            $fieldWeight = $isCritical ? self::WEIGHT_FIELD_CRITICAL : self::WEIGHT_FIELD_NORMAL;
            $weightedSum += $exposedConfidence * $fieldWeight;
            $totalWeight += $fieldWeight;

            $fieldWarnings = [];
            if ($isCritical && ! $rule) {
                $fieldWarnings[] = ['code' => 'CRITICAL_RULE_FAILED', 'msg' => $entry['rule_reason'] ?? ''];
            }
            if (abs($self - $judge) > 0.5) {
                $fieldWarnings[] = ['code' => 'SELF_VS_JUDGE_DISAGREE', 'msg' => sprintf('Hai bước AI chấm điểm khác nhau: %.2f vs %.2f', $self, $judge)];
            }
            if ($entry && ($entry['judge_action'] ?? 'keep') === 'flag_low_conf') {
                $fieldWarnings[] = ['code' => 'JUDGE_FLAGGED', 'msg' => $entry['judge_reason'] ?? ''];
            }

            $aggregated[$key] = [
                'value' => $value,
                'final_confidence' => round($exposedConfidence, 3),
                'signals' => [
                    'self_report' => round($self, 3),
                    'rule_passed' => $rule,
                    'judge_confidence' => round($judge, 3),
                ],
                'critical' => $isCritical,
                'flagged' => $exposedConfidence < $thresholdMedMin,
                'warnings' => $fieldWarnings,
            ];
            $confidencePerField[$key] = round($exposedConfidence, 3);

            if (! empty($fieldWarnings)) {
                array_push($warningsFields, ...array_map(fn ($w) => $w + ['field' => $key], $fieldWarnings));
            }
        }

        $overall = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
        $quality = $this->bucketize($overall, $thresholdHigh, $thresholdMedMin);
        $requiresReview = $overall < $thresholdHigh;

        // Document-level warnings
        $docWarnings = [];
        if ($overall < $thresholdMedMin) {
            $docWarnings[] = ['field' => '_global', 'code' => 'LOW_CONFIDENCE', 'msg' => sprintf('Độ tin cậy tổng thấp (%.2f / ngưỡng %.2f) — cần KSNB kiểm tra lại.', $overall, $thresholdMedMin)];
        } elseif ($overall < $thresholdHigh) {
            $docWarnings[] = ['field' => '_global', 'code' => 'MEDIUM_CONFIDENCE', 'msg' => sprintf('Độ tin cậy tổng trung bình (%.2f / ngưỡng cao %.2f) — nên rà soát.', $overall, $thresholdHigh)];
        }
        if (! empty($criticalFailures)) {
            $docWarnings[] = ['field' => '_global', 'code' => 'CRITICAL_FIELDS_FAILED', 'msg' => 'Trường quan trọng sai định dạng: ' . implode(', ', $criticalFailures)];
        }
        if (! empty($signalDisagreements)) {
            $docWarnings[] = ['field' => '_global', 'code' => 'MULTI_SIGNAL_DISAGREE', 'msg' => 'AI chấm điểm không đồng nhất ở các trường: ' . implode(', ', $signalDisagreements)];
        }
        if ($imageQualityNote) {
            $lower = mb_strtolower($imageQualityNote);
            foreach (['blurry', 'skewed', 'obscured', 'partial', 'low light', 'wrinkled', 'mờ', 'nghiêng', 'che', 'nhăn'] as $bad) {
                if (str_contains($lower, $bad)) {
                    $docWarnings[] = ['field' => '_global', 'code' => 'LOW_IMAGE_QUALITY', 'msg' => $imageQualityNote];
                    break;
                }
            }
        }

        // Review priority: list keys sorted ascending by confidence (lowest first), only flagged
        $reviewPriority = [];
        foreach ($aggregated as $k => $a) {
            if ($a['flagged'] || $a['final_confidence'] < $thresholdHigh) {
                $reviewPriority[$k] = $a['final_confidence'];
            }
        }
        asort($reviewPriority);
        $reviewPriority = array_keys($reviewPriority);

        // Plain key=>value RAW map (for KSNB copy-paste, ignoring metadata)
        $kvFlatRaw = [];
        foreach ($aggregated as $k => $a) {
            $kvFlatRaw[$k] = $a['value'];
        }

        return [
            'aggregated' => $aggregated,
            'key_values_raw_map' => $kvFlatRaw,
            'confidence_per_field' => $confidencePerField,
            'overall_confidence' => round($overall, 3),
            'quality' => $quality,
            'requires_review' => $requiresReview,
            'warnings' => array_merge($warningsFields, $docWarnings),
            'review_priority_fields' => $reviewPriority,
        ];
    }

    private function bucketize(float $confidence, float $high, float $medMin): string
    {
        return match (true) {
            $confidence >= $high => 'high',
            $confidence >= $medMin => 'medium',
            default => 'low',
        };
    }
}
