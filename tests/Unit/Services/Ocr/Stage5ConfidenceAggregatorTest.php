<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\Stage5ConfidenceAggregator;
use PHPUnit\Framework\TestCase;

class Stage5ConfidenceAggregatorTest extends TestCase
{
    private Stage5ConfidenceAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agg = new Stage5ConfidenceAggregator(thresholdHigh: 0.85, thresholdMedMin: 0.50);
    }

    public function test_happy_path_high_confidence(): void
    {
        $r = $this->agg->aggregate(
            keyValuesRaw: ['so_cccd' => '001234567890', 'ho_ten' => 'NGUYỄN VĂN A'],
            stage2SelfConf: ['so_cccd' => 0.97, 'ho_ten' => 0.95],
            stage3PerField: [
                'so_cccd' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.95, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'ho_ten' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.95, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
            ],
            criticalFieldKeys: ['so_cccd', 'ho_ten'],
        );

        // AC R3/AC-AI-01: exposed confidence = self_report (Stage 2). Rule + judge → signals only.
        $this->assertEqualsWithDelta(0.97, $r['aggregated']['so_cccd']['final_confidence'], 0.005);
        $this->assertEqualsWithDelta(0.95, $r['aggregated']['ho_ten']['final_confidence'], 0.005);
        // overall = weighted avg of self (critical 2x): (0.97*2 + 0.95*2) / 4 = 0.96
        $this->assertEqualsWithDelta(0.96, $r['overall_confidence'], 0.005);
        $this->assertSame('high', $r['quality']);
        $this->assertFalse($r['requires_review']);
        $this->assertEmpty($r['review_priority_fields']);
    }

    public function test_overall_confidence_is_weighted_average_of_self_report(): void
    {
        // AC R3 sample TQ_ID_front.jpg: 3 critical + 3 normal, self values per tester bug 4
        $r = $this->agg->aggregate(
            keyValuesRaw: [
                'id_number' => '440101199001011234',  // critical
                'gender' => '男',                       // normal
                'ethnicity' => '土家',                  // normal
                'address' => '广州市天河区',            // normal
                'full_name' => '彭友',                  // critical
                'date_of_birth' => '1990-01-01',       // critical
            ],
            stage2SelfConf: [
                'id_number' => 0.964,
                'gender' => 0.964,
                'ethnicity' => 0.958,
                'address' => 0.964,
                'full_name' => 0.964,
                'date_of_birth' => 0.561,
            ],
            stage3PerField: [
                'id_number' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.964, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'gender' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.964, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'ethnicity' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.958, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'address' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.964, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'full_name' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.964, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
                'date_of_birth' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.561, 'judge_reason' => 'low confidence', 'judge_action' => 'keep'],
            ],
            criticalFieldKeys: ['id_number', 'full_name', 'date_of_birth'],
        );

        // overall = (0.964*2 + 0.964*1 + 0.958*1 + 0.964*1 + 0.964*2 + 0.561*2) / 9 = 7.864/9 = 0.874
        $this->assertEqualsWithDelta(0.874, $r['overall_confidence'], 0.005);
        $this->assertEqualsWithDelta(0.561, $r['aggregated']['date_of_birth']['final_confidence'], 0.005);
        // overall 0.874 > 0.85 nhưng date_of_birth có self=0.561 ∈ [0.5, 0.7) → V1 Resource sẽ flag (AC R4)
        // requires_review ở Stage 5 chỉ check overall < 0.85; V1 Resource override OR anyFlagged
        $this->assertFalse($r['requires_review']);
    }

    public function test_critical_field_rule_fail_caps_low_and_marks_review(): void
    {
        $r = $this->agg->aggregate(
            keyValuesRaw: ['so_cccd' => '0012345', 'ho_ten' => 'NGUYỄN VĂN A'],
            stage2SelfConf: ['so_cccd' => 0.85, 'ho_ten' => 0.95],
            stage3PerField: [
                'so_cccd' => ['rule_passed' => false, 'rule_reason' => 'must be 12 digits', 'judge_confidence' => 0.4, 'judge_reason' => 'too short', 'judge_action' => 'flag_low_conf'],
                'ho_ten' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.95, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
            ],
            criticalFieldKeys: ['so_cccd', 'ho_ten'],
        );

        $this->assertLessThanOrEqual(0.4, $r['aggregated']['so_cccd']['final_confidence']);
        $this->assertTrue($r['requires_review']);
        $codes = array_column($r['warnings'], 'code');
        $this->assertContains('CRITICAL_FIELDS_FAILED', $codes);
        $this->assertContains('so_cccd', $r['review_priority_fields']);
    }

    public function test_low_quality_bucket(): void
    {
        $r = $this->agg->aggregate(
            keyValuesRaw: ['x' => 'abc'],
            stage2SelfConf: ['x' => 0.2],
            stage3PerField: [
                'x' => ['rule_passed' => false, 'rule_reason' => 'bad', 'judge_confidence' => 0.1, 'judge_reason' => 'bad', 'judge_action' => 'flag_low_conf'],
            ],
            criticalFieldKeys: [],
        );
        $this->assertSame('low', $r['quality']);
        $this->assertTrue($r['requires_review']);
    }

    public function test_image_quality_warning_propagates(): void
    {
        $r = $this->agg->aggregate(
            keyValuesRaw: ['x' => 'abc'],
            stage2SelfConf: ['x' => 0.9],
            stage3PerField: [
                'x' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.9, 'judge_reason' => 'ok', 'judge_action' => 'keep'],
            ],
            criticalFieldKeys: [],
            imageQualityNote: 'Ảnh hơi mờ và bị nghiêng',
        );
        $codes = array_column($r['warnings'], 'code');
        $this->assertContains('LOW_IMAGE_QUALITY', $codes);
    }

    public function test_signal_disagreement_warning(): void
    {
        $r = $this->agg->aggregate(
            keyValuesRaw: ['amount' => '1500000'],
            stage2SelfConf: ['amount' => 0.95],
            stage3PerField: [
                'amount' => ['rule_passed' => true, 'rule_reason' => 'ok', 'judge_confidence' => 0.2, 'judge_reason' => 'conflicts words', 'judge_action' => 'flag_low_conf'],
            ],
            criticalFieldKeys: ['amount'],
        );
        $codes = array_column($r['warnings'], 'code');
        $this->assertContains('MULTI_SIGNAL_DISAGREE', $codes);
    }

}
