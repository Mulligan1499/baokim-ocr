<?php

namespace App\Services\Ocr;

/**
 * Stage 5 — Multi-signal confidence aggregator (from skill ocr-confidence-aggregator).
 *
 * Inputs (per field): self_report (Stage 2), rule_passed (Stage 3a), judge_confidence (Stage 3b).
 * Output: aggregated_key_values[] + overall_confidence + quality bucket + requires_review + warnings.
 *
 * Formula: final = self×0.3 + rule×0.4 + judge×0.3
 *   IF critical AND rule_passed=false → cap final at 0.4
 * Overall: weighted average by critical(2)/normal(1).
 * Buckets: high ≥ 0.85 | medium 0.5..0.85 | low < 0.5 (requires_review when < 0.85).
 */
class Stage5ConfidenceAggregator
{
    private const WEIGHT_SELF = 0.3;
    private const WEIGHT_RULE = 0.4;
    private const WEIGHT_JUDGE = 0.3;

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

            $final = ($self * self::WEIGHT_SELF)
                + (($rule ? 1.0 : 0.0) * self::WEIGHT_RULE)
                + ($judge * self::WEIGHT_JUDGE);

            if ($isCritical && ! $rule) {
                $criticalFailures[] = $key;
                $final = min($final, self::RULE_FAIL_CAP_CRITICAL);
            }

            // Signal disagreement: |self - judge| > 0.5
            if (abs($self - $judge) > 0.5) {
                $signalDisagreements[] = $key;
            }

            $fieldWeight = $isCritical ? self::WEIGHT_FIELD_CRITICAL : self::WEIGHT_FIELD_NORMAL;
            $weightedSum += $final * $fieldWeight;
            $totalWeight += $fieldWeight;

            $fieldWarnings = [];
            if ($isCritical && ! $rule) {
                $fieldWarnings[] = ['code' => 'CRITICAL_RULE_FAILED', 'msg' => $entry['rule_reason'] ?? ''];
            }
            if (abs($self - $judge) > 0.5) {
                $fieldWarnings[] = ['code' => 'SELF_VS_JUDGE_DISAGREE', 'msg' => sprintf('self=%.2f judge=%.2f', $self, $judge)];
            }
            if ($entry && ($entry['judge_action'] ?? 'keep') === 'flag_low_conf') {
                $fieldWarnings[] = ['code' => 'JUDGE_FLAGGED', 'msg' => $entry['judge_reason'] ?? ''];
            }

            $aggregated[$key] = [
                'value' => $value,
                'final_confidence' => round($final, 3),
                'signals' => [
                    'self_report' => round($self, 3),
                    'rule_passed' => $rule,
                    'judge_confidence' => round($judge, 3),
                ],
                'critical' => $isCritical,
                'flagged' => $final < $thresholdMedMin,
                'warnings' => $fieldWarnings,
            ];
            $confidencePerField[$key] = round($final, 3);

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
            $docWarnings[] = ['field' => '_global', 'code' => 'LOW_CONFIDENCE', 'msg' => sprintf('Overall confidence %.2f below %.2f', $overall, $thresholdMedMin)];
        } elseif ($overall < $thresholdHigh) {
            $docWarnings[] = ['field' => '_global', 'code' => 'MEDIUM_CONFIDENCE', 'msg' => sprintf('Overall confidence %.2f below %.2f — review recommended', $overall, $thresholdHigh)];
        }
        if (! empty($criticalFailures)) {
            $docWarnings[] = ['field' => '_global', 'code' => 'CRITICAL_FIELDS_FAILED', 'msg' => 'Failed critical fields: ' . implode(', ', $criticalFailures)];
        }
        if (! empty($signalDisagreements)) {
            $docWarnings[] = ['field' => '_global', 'code' => 'MULTI_SIGNAL_DISAGREE', 'msg' => 'Disagree on: ' . implode(', ', $signalDisagreements)];
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
