<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\Stage3RuleValidator;
use PHPUnit\Framework\TestCase;

class Stage3RuleValidatorTest extends TestCase
{
    private Stage3RuleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Stage3RuleValidator();
    }

    public function test_cccd_12_digits_passes(): void
    {
        $r = $this->validator->validate(['so_cccd' => '001234567890'], 'cccd');
        $this->assertTrue($r['so_cccd']['rule_passed']);
    }

    public function test_cccd_wrong_length_fails(): void
    {
        $r = $this->validator->validate(['so_cccd' => '0012345'], 'cccd');
        $this->assertFalse($r['so_cccd']['rule_passed']);
    }

    public function test_cccd_with_letters_fails(): void
    {
        $r = $this->validator->validate(['so_cccd' => '00123456789A'], 'cccd');
        $this->assertFalse($r['so_cccd']['rule_passed']);
    }

    public function test_phone_vn_valid(): void
    {
        foreach (['0987654321', '0301234567', '0512345678'] as $phone) {
            $r = $this->validator->validate(['phone' => $phone], 'cccd');
            $this->assertTrue($r['phone']['rule_passed'], "Phone {$phone} should pass");
        }
    }

    public function test_phone_vn_wrong_prefix_fails(): void
    {
        $r = $this->validator->validate(['phone' => '0212345678'], 'cccd');
        $this->assertFalse($r['phone']['rule_passed']);
    }

    public function test_email_valid_and_invalid(): void
    {
        $r1 = $this->validator->validate(['email' => 'a@b.com'], 'cccd');
        $r2 = $this->validator->validate(['email' => 'not an email'], 'cccd');
        $this->assertTrue($r1['email']['rule_passed']);
        $this->assertFalse($r2['email']['rule_passed']);
    }

    public function test_empty_value_passes(): void
    {
        $r = $this->validator->validate(['so_cccd' => ''], 'cccd');
        $this->assertTrue($r['so_cccd']['rule_passed']);
        $this->assertStringContainsString('Trống', $r['so_cccd']['rule_reason']);
    }

    public function test_date_dd_mm_yyyy_passes(): void
    {
        $r = $this->validator->validate(['ngay_sinh' => '01/01/1990'], 'cccd');
        $this->assertTrue($r['ngay_sinh']['rule_passed']);
    }

    public function test_date_in_future_fails(): void
    {
        $future = date('d/m/Y', strtotime('+10 years'));
        $r = $this->validator->validate(['date' => $future], 'cccd');
        $this->assertFalse($r['date']['rule_passed']);
    }

    public function test_mst_10_digits_passes(): void
    {
        $r = $this->validator->validate(['mst' => '0123456789'], 'gpkd');
        $this->assertTrue($r['mst']['rule_passed']);
    }

    public function test_mst_10_3_passes(): void
    {
        $r = $this->validator->validate(['mst' => '0123456789-001'], 'gpkd');
        $this->assertTrue($r['mst']['rule_passed']);
    }

    public function test_unknown_field_passes(): void
    {
        $r = $this->validator->validate(['some_random_field' => 'hello world'], 'cccd');
        $this->assertTrue($r['some_random_field']['rule_passed']);
    }
}
