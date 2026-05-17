<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessOcrDocument;
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
        // bind a fake LlmClient (no network)
        $this->app->instance(LlmClient::class, new FakeLlmClient());
        Storage::fake('local');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/ocr/process')->assertStatus(401);
    }

    public function test_invalid_file_returns_422(): void
    {
        $file = UploadedFile::fake()->createWithContent('fake.docx', 'irrelevant');
        $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/ocr/process', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_full_pipeline_runs_with_fake_llm(): void
    {
        $file = UploadedFile::fake()->image('cccd.jpg', 800, 600);

        $resp = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/ocr/process', ['file' => $file]);

        // Sync queue: 200 with full result. Async queue: 202 with status=pending.
        $this->assertContains($resp->status(), [200, 202], 'POST should return 200 (sync) or 202 (async)');
        $resp->assertJson(['duplicate' => false]);
        $documentId = $resp->json('document_id');
        $doc = OcrDocument::find($documentId);
        $this->assertSame(OcrDocument::STATUS_DONE, $doc->status);

        $extraction = OcrExtraction::where('document_id', $documentId)->firstOrFail();
        $this->assertSame('cccd', $extraction->doc_type);
        $this->assertSame('vi', $extraction->language_detected);
        $this->assertGreaterThanOrEqual(0.5, (float) $extraction->confidence_overall);

        $kv = $extraction->key_values;
        $this->assertArrayHasKey('so_cccd', $kv);
        $this->assertSame('001234567890', $kv['so_cccd']);

        // RAW vs masked
        $this->assertStringContainsString('001234567890', $extraction->text_full);
        $this->assertStringContainsString('001234***890', $extraction->text_full_masked);

        // Audit stages 0..6 all touched
        $stages = OcrAuditLog::where('document_id', $documentId)->pluck('stage')->toArray();
        foreach ([0, 1, 2, 3, 4, 5, 6] as $s) {
            $this->assertContains($s, $stages, "Missing audit for stage {$s}");
        }
    }

    public function test_get_with_view_masked_returns_masked_data(): void
    {
        $file = UploadedFile::fake()->image('cccd.jpg', 800, 600);
        $resp = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/ocr/process', ['file' => $file]);
        $id = $resp->json('document_id');
        ProcessOcrDocument::dispatchSync($id);

        $rawJson = $this->withHeader('X-API-Key', $this->apiKey)
            ->get("/api/ocr/{$id}")->json();
        $this->assertSame('raw', $rawJson['extraction']['view_mode']);
        $this->assertSame('001234567890', $rawJson['extraction']['key_values']['so_cccd']);

        $maskedJson = $this->withHeader('X-API-Key', $this->apiKey)
            ->get("/api/ocr/{$id}?view=masked")->json();
        $this->assertSame('masked', $maskedJson['extraction']['view_mode']);
        $this->assertSame('001234***890', $maskedJson['extraction']['key_values']['so_cccd']);
    }

    public function test_dedupe_same_file_returns_same_id(): void
    {
        // Need a real image (validation enforces image/* or pdf MIME). Save once + reuse the path
        // so both uploads hit the same hash.
        $file1 = UploadedFile::fake()->image('a.jpg', 50, 50);
        $bytes = file_get_contents($file1->getRealPath());
        $tmp1 = tempnam(sys_get_temp_dir(), 'ocr1') . '.jpg';
        $tmp2 = tempnam(sys_get_temp_dir(), 'ocr2') . '.jpg';
        file_put_contents($tmp1, $bytes);
        file_put_contents($tmp2, $bytes);

        $u1 = new UploadedFile($tmp1, 'a.jpg', 'image/jpeg', null, true);
        $u2 = new UploadedFile($tmp2, 'b.jpg', 'image/jpeg', null, true);

        $r1 = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/ocr/process', ['file' => $u1]);
        $r2 = $this->withHeader('X-API-Key', $this->apiKey)
            ->post('/api/ocr/process', ['file' => $u2]);

        $this->assertSame($r1->json('document_id'), $r2->json('document_id'));
        $r2->assertJson(['duplicate' => true]);
    }
}

/**
 * In-memory LlmClient stub. Returns deterministic JSON depending on which logical model is requested.
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
