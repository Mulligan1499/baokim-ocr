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

        // (0.97*0.3 + 1.0*0.4 + 0.95*0.3) = 0.291 + 0.4 + 0.285 = 0.976
        // (0.95*0.3 + 1.0*0.4 + 0.95*0.3) = 0.285 + 0.4 + 0.285 = 0.97
        $this->assertEqualsWithDelta(0.976, $r['aggregated']['so_cccd']['final_confidence'], 0.005);
        $this->assertEqualsWithDelta(0.97, $r['aggregated']['ho_ten']['final_confidence'], 0.005);
        $this->assertSame('high', $r['quality']);
        $this->assertFalse($r['requires_review']);
        $this->assertEmpty($r['review_priority_fields']);
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
