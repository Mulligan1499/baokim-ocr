<?php

namespace Tests\Feature\Feedback;

use App\Models\OcrUserAction;
use App\Services\Llm\LlmClient;
use Database\Seeders\FakeActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E test cho killer demo feature `ocr:analyze-actions`.
 *
 * Flow:
 *   1. Seed 37 fake actions với 3 pattern rõ ràng (A/C/E)
 *   2. Bind mock LlmClient trả về JSON response cố định (không gọi Gemini thật)
 *   3. Chạy artisan command
 *   4. Assert report markdown chứa đúng patterns
 *   5. Assert dry-run KHÔNG mark analyzed, non-dry-run CÓ mark
 */
class AnalyzeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindMockLlm();
        $this->seed(FakeActionSeeder::class);
    }

    public function test_dry_run_creates_report_but_does_not_mark_analyzed(): void
    {
        $this->assertSame(37, OcrUserAction::whereNull('analyzed_at')->count());

        $this->artisan('ocr:analyze-actions', [
            '--since' => '7days',
            '--min-count' => '3',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Patterns detected')
            ->expectsOutputToContain('--dry-run: actions NOT marked analyzed')
            ->assertSuccessful();

        $this->assertSame(
            37,
            OcrUserAction::whereNull('analyzed_at')->count(),
            'Dry-run KHÔNG được mark analyzed_at',
        );

        $reports = glob(base_path('reports/skill-update-*.md'));
        $this->assertNotEmpty($reports, 'Phải tạo file report markdown');

        $latest = end($reports);
        $content = file_get_contents($latest);

        // Verify 3 patterns được nhận diện
        $this->assertStringContainsString('A-cccd-so_cccd', $content, 'Pattern A CCCD phải có trong report');
        $this->assertStringContainsString('E-contract_zh-ethnicity', $content, 'Pattern E skip phải có');
        $this->assertStringContainsString('C-invoice-total_amount', $content, 'Pattern C mark_wrong phải có');

        // Verify cấu trúc report theo skill
        $this->assertStringContainsString('Observation:', $content);
        $this->assertStringContainsString('Hypothesis:', $content);
        $this->assertStringContainsString('Proposed diff:', $content);

        @unlink($latest);
    }

    public function test_non_dry_run_marks_actions_analyzed(): void
    {
        $this->artisan('ocr:analyze-actions', [
            '--since' => '7days',
            '--min-count' => '3',
        ])->assertSuccessful();

        $this->assertSame(
            0,
            OcrUserAction::whereNull('analyzed_at')->count(),
            'Non-dry-run phải mark tất cả actions trong window',
        );

        // Cleanup latest report
        $reports = glob(base_path('reports/skill-update-*.md'));
        foreach ($reports as $r) {
            @unlink($r);
        }
    }

    public function test_no_patterns_when_min_count_too_high(): void
    {
        $this->artisan('ocr:analyze-actions', [
            '--since' => '7days',
            '--min-count' => '999',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('No actionable patterns found')
            ->assertSuccessful();
    }

    /**
     * Mock LlmClient trả về JSON match 3 pattern seeder tạo ra.
     * Tránh gọi Gemini thật trong CI/test.
     */
    private function bindMockLlm(): void
    {
        $mockResponse = [
            'content' => json_encode([
                'summary' => [
                    'total_patterns_detected' => 3,
                    'actionable' => 3,
                    'deferred' => 0,
                    'needs_new_skill' => 0,
                ],
                'patterns' => [
                    [
                        'pattern_id' => 'A-cccd-so_cccd-001',
                        'category' => 'A',
                        'severity' => 'HIGH',
                        'observation' => 'KSNB sửa so_cccd 10/12 lần (83% edit rate) trên doc CCCD — pattern OCR đọc nhầm chữ O thành 0.',
                        'hypothesis' => 'Stage 2 vision prompt chưa hint rõ "ký tự cuối CCCD là số, không phải chữ O".',
                        'target_skill_file' => '.claude/skills/dev-vision-prompt-designer/SKILL.md',
                        'target_runtime_file' => 'resources/prompts/stage2_vision.md',
                        'proposed_diff' => "```diff\n+ CCCD must be exactly 12 digits. Validate last char is digit, not letter O.\n```",
                        'confidence' => 0.92,
                        'suggested_action' => 'create_pr',
                    ],
                    [
                        'pattern_id' => 'E-contract_zh-ethnicity-002',
                        'category' => 'E',
                        'severity' => 'MEDIUM',
                        'observation' => 'KSNB skip field ethnicity 20/20 lần trên contract_zh — field này không cần thiết.',
                        'hypothesis' => 'Taxonomy contract_zh đang list ethnicity là normal field, nhưng KSNB workflow không dùng.',
                        'target_skill_file' => null,
                        'target_runtime_file' => 'config/ocr_doc_taxonomy.php',
                        'proposed_diff' => "```diff\n- 'ethnicity',\n```",
                        'confidence' => 0.88,
                        'suggested_action' => 'create_pr',
                    ],
                    [
                        'pattern_id' => 'C-invoice-total_amount-003',
                        'category' => 'C',
                        'severity' => 'HIGH',
                        'observation' => 'KSNB mark_wrong total_amount 5 lần trên invoice — hard signal sai số.',
                        'hypothesis' => 'Confidence threshold cho total_amount đang quá cao (0.95) trong khi OCR sai.',
                        'target_skill_file' => '.claude/skills/ocr-confidence-aggregator/SKILL.md',
                        'target_runtime_file' => 'config/ocr.php',
                        'proposed_diff' => "```diff\n- 'high' => 0.85,\n+ 'high' => 0.90,\n```",
                        'confidence' => 0.85,
                        'suggested_action' => 'create_pr',
                    ],
                ],
                'deferred' => [],
            ], JSON_UNESCAPED_UNICODE),
            'latency_ms' => 1234,
            'input_tokens' => 500,
            'output_tokens' => 800,
            'model' => 'mock-llm',
        ];

        $this->app->instance(LlmClient::class, new class($mockResponse) implements LlmClient {
            public function __construct(private array $mockResponse) {}

            public function messages(
                string $modelLogicalName,
                string $systemPrompt,
                array $userContent,
                int $maxTokens = 4096,
            ): array {
                return $this->mockResponse;
            }

            public function providerName(): string
            {
                return 'mock';
            }
        });
    }
}
