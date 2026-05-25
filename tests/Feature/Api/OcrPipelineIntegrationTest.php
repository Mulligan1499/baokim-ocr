<?php

namespace Tests\Feature\Api;

use App\Models\OcrAuditLog;
use App\Models\OcrDocument;
use App\Models\OcrExtraction;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-key-abc123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ocr.api_key' => $this->apiKey]);
        $this->app->instance(LlmClient::class, new FakeLlmClient());
        Storage::fake('local');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->postJson('/api/v1/ocr/extract');
        $resp->assertStatus(401);
        $resp->assertJsonStructure(['error_code', 'message_vi', 'message_en', 'request_id']);
    }

    public function test_invalid_file_returns_400(): void
    {
        $file = UploadedFile::fake()->createWithContent('fake.docx', 'irrelevant');
        $resp = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/v1/ocr/extract', ['file' => $file]);
        // V1 error schema R8: INVALID_FILE_FORMAT → 400
        $this->assertContains($resp->status(), [400, 422]);
        $resp->assertJsonStructure(['error_code', 'message_vi', 'message_en']);
    }

    public function test_full_pipeline_runs_with_fake_llm(): void
    {
        $file = UploadedFile::fake()->image('cccd.jpg', 800, 600);

        $resp = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/v1/ocr/extract', ['file' => $file]);

        $this->assertContains($resp->status(), [200, 202]);

        $documentId = $resp->json('document_id');
        $doc = OcrDocument::find($documentId);
        $this->assertSame(OcrDocument::STATUS_DONE, $doc->status);
        $this->assertNotNull($doc->request_id, 'request_id UUID phải có (AC-01)');

        $extraction = OcrExtraction::where('document_id', $documentId)->firstOrFail();
        $this->assertSame('cccd', $extraction->doc_type);
        $this->assertSame('vi', $extraction->language_detected);
        $this->assertGreaterThanOrEqual(0.5, (float) $extraction->confidence_overall);

        $kv = $extraction->key_values;
        $this->assertArrayHasKey('so_cccd', $kv);

        // RAW vs masked (AC R6 pattern: giữ 4 ký tự cuối)
        $this->assertStringContainsString('001234567890', $extraction->text_full);
        $this->assertStringContainsString('********7890', $extraction->text_full_masked);

        // AC-01 response shape: result với key_value_pairs array
        $result = $resp->json('result');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('raw_text', $result);
        $this->assertArrayHasKey('key_value_pairs', $result);
        $this->assertArrayHasKey('language_detected', $result);
        $this->assertArrayHasKey('overall_confidence', $result);
        $this->assertArrayHasKey('ai_model_version', $result);

        // Audit stages 0..6 all touched
        $stages = OcrAuditLog::where('document_id', $documentId)->pluck('stage')->toArray();
        foreach ([0, 1, 2, 3, 4, 5, 6] as $s) {
            $this->assertContains($s, $stages, "Missing audit for stage {$s}");
        }
    }

    public function test_history_lookup_by_request_id_and_numeric_id(): void
    {
        $file = UploadedFile::fake()->image('cccd.jpg', 800, 600);
        $resp = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/v1/ocr/extract', ['file' => $file]);
        $requestId = $resp->json('request_id');
        $documentId = $resp->json('document_id');

        // Lookup by UUID
        $byUuid = $this->withHeader('X-API-Key', $this->apiKey)
            ->get("/api/v1/ocr/history/{$requestId}");
        $byUuid->assertStatus(200);
        $this->assertSame($requestId, $byUuid->json('request_id'));

        // Lookup by numeric id (alias)
        $byId = $this->withHeader('X-API-Key', $this->apiKey)
            ->get("/api/v1/ocr/history/{$documentId}");
        $byId->assertStatus(200);
        $this->assertSame($requestId, $byId->json('request_id'));
    }

    public function test_dedupe_same_file_returns_cached_true(): void
    {
        $file1 = UploadedFile::fake()->image('a.jpg', 50, 50);
        $bytes = file_get_contents($file1->getRealPath());
        $tmp1 = tempnam(sys_get_temp_dir(), 'ocr1') . '.jpg';
        $tmp2 = tempnam(sys_get_temp_dir(), 'ocr2') . '.jpg';
        file_put_contents($tmp1, $bytes);
        file_put_contents($tmp2, $bytes);

        $u1 = new UploadedFile($tmp1, 'a.jpg', 'image/jpeg', null, true);
        $u2 = new UploadedFile($tmp2, 'b.jpg', 'image/jpeg', null, true);

        $r1 = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/v1/ocr/extract', ['file' => $u1]);

        $r2 = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/v1/ocr/extract', ['file' => $u2]);

        $this->assertSame($r1->json('request_id'), $r2->json('request_id'));
        $this->assertTrue($r2->json('cached'), 'AC-E05: lần upload thứ 2 phải có cached=true');
    }
}

/**
 * In-memory LlmClient stub. Returns deterministic JSON per logical model name.
 */
class FakeLlmClient implements LlmClient
{
    public function providerName(): string
    {
        return 'fake';
    }

    public function messages(string $modelLogicalName, string $systemPrompt, array $userContent, int $maxTokens = 4096): array
    {
        $body = match ($modelLogicalName) {
            'classifier' => json_encode([
                'document_detected' => true,
                'document_type' => 'cccd',
                'language_detected' => 'vi',
                'image_quality_note' => 'Clear front-side CCCD',
                'extraction_strategy_hint' => 'Header has so_cccd, ho_ten, ngay_sinh',
                'classifier_confidence' => 0.96,
            ]),
            'extractor' => json_encode([
                'document_detected' => true,
                'document_type_actual' => 'cccd',
                'language_detected' => 'vi',
                'image_quality_note' => 'Clear front-side CCCD',
                'raw_text' => 'Số: 001234567890 Họ tên: NGUYỄN VĂN A Ngày sinh: 01/01/1990',
                'translation_vi' => null,
                'key_values' => [
                    ['key' => 'so_cccd', 'value' => '001234567890', 'confidence' => 0.97, 'critical' => true, 'validation_passed' => true, 'flagged_low_confidence' => false, 'value_translated_vi' => null],
                    ['key' => 'ho_ten', 'value' => 'NGUYỄN VĂN A', 'confidence' => 0.95, 'critical' => true, 'validation_passed' => true, 'flagged_low_confidence' => false, 'value_translated_vi' => null],
                    ['key' => 'ngay_sinh', 'value' => '01/01/1990', 'confidence' => 0.93, 'critical' => true, 'validation_passed' => true, 'flagged_low_confidence' => false, 'value_translated_vi' => null],
                ],
                'overall_confidence_self_report' => 0.94,
                'warnings_self_report' => [],
            ]),
            'judge' => json_encode([
                'evaluations' => [
                    ['key' => 'so_cccd', 'judge_confidence' => 0.95, 'judge_reason' => '12 digits OK', 'judge_action' => 'keep'],
                    ['key' => 'ho_ten', 'judge_confidence' => 0.93, 'judge_reason' => 'Name OK', 'judge_action' => 'keep'],
                    ['key' => 'ngay_sinh', 'judge_confidence' => 0.92, 'judge_reason' => 'Date OK', 'judge_action' => 'keep'],
                ],
            ]),
            default => '{}',
        };

        return [
            'content' => $body,
            'latency_ms' => 10,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'model' => "fake-{$modelLogicalName}",
        ];
    }
}
