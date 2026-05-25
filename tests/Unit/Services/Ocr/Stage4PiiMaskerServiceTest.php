<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\Stage4PiiMaskerService;
use PHPUnit\Framework\TestCase;

class Stage4PiiMaskerServiceTest extends TestCase
{
    private Stage4PiiMaskerService $masker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masker = new Stage4PiiMaskerService();
    }

    public function test_masks_cccd_12_digits(): void
    {
        $result = $this->masker->maskText('CCCD 001234567890 cấp ngày');
        // AC R6: giữ 4 ký tự cuối, mask phần đầu
        $this->assertSame('CCCD ********7890 cấp ngày', $result['masked']);
        $this->assertSame(1, $result['detected']['cccd_12']);
    }

    public function test_masks_vn_phone(): void
    {
        $result = $this->masker->maskText('Liên hệ 0987654321 hoặc 0901234567');
        $this->assertStringContainsString('******4321', $result['masked']);
        $this->assertStringContainsString('******4567', $result['masked']);
        $this->assertSame(2, $result['detected']['phone_vn']);
    }

    public function test_masks_email(): void
    {
        $result = $this->masker->maskText('email: john.doe@example.com');
        // AC R6: giữ 4 ký tự cuối của local part
        $this->assertSame('email: ****.doe@example.com', $result['masked']);
        $this->assertSame(1, $result['detected']['email']);
    }

    public function test_masks_date_of_birth(): void
    {
        $result = $this->masker->maskText('Sinh ngày 01/01/1990');
        $this->assertSame('Sinh ngày **/**/1990', $result['masked']);
    }

    public function test_masks_ipv4(): void
    {
        $result = $this->masker->maskText('Connection from 192.168.1.100');
        $this->assertSame('Connection from 192.168.*.*', $result['masked']);
    }

    public function test_returns_unchanged_when_no_pii(): void
    {
        $text = 'Hello world, no PII here';
        $result = $this->masker->maskText($text);
        $this->assertSame($text, $result['masked']);
        $this->assertEmpty($result['detected']);
    }

    public function test_mask_key_values_returns_per_key_map(): void
    {
        $result = $this->masker->maskKeyValues([
            'so_cccd' => '001234567890',
            'phone' => '0987654321',
            'email' => 'jane@example.com',
            'ho_ten' => 'Nguyễn Văn An', // best-effort name masker
        ]);
        $this->assertSame('********7890', $result['masked_kv']['so_cccd']);
        $this->assertStringContainsString('******4321', $result['masked_kv']['phone']);
        $this->assertStringContainsString('jane@', $result['masked_kv']['email']);
        $this->assertArrayHasKey('cccd_12', $result['detected']);
        $this->assertArrayHasKey('phone_vn', $result['detected']);
    }

    public function test_handles_non_string_values(): void
    {
        $result = $this->masker->maskKeyValues(['age' => 25, 'amount' => null]);
        $this->assertSame(25, $result['masked_kv']['age']);
        $this->assertNull($result['masked_kv']['amount']);
    }
}
