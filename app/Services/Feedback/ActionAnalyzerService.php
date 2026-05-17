<?php

namespace App\Services\Feedback;

use App\Repositories\OcrUserActionRepository;
use App\Services\Llm\ContentBlocks;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * BKM03 Service layer — business logic của feedback loop.
 *
 * 1. Aggregate raw `ocr_user_actions` từ Repository (no logic ở repo, no
 *    query ở loop — BKM04).
 * 2. Pre-filter patterns theo heuristics đơn giản (min count, edit rate) để
 *    giảm token gửi LLM.
 * 3. Gọi LlmClient (provider-agnostic, reuse Stage 2 infrastructure) với
 *    prompt từ resources/prompts/feedback_refiner.md để classify category
 *    + generate diff đề xuất.
 * 4. Trả về structured patterns + summary cho command consume.
 *
 * Skill encoded: feedback-to-skill-refiner v0.1.0
 */
class ActionAnalyzerService
{
    public function __construct(
        private OcrUserActionRepository $actions,
        private LlmClient $llm,
    ) {}

    /**
     * @return array {
     *   summary: { total_patterns_detected, actionable, deferred, needs_new_skill },
     *   patterns: array<int, array>,
     *   deferred: array<int, array>,
     *   stats: { actions_analyzed, since, llm_latency_ms, llm_input_tokens, llm_output_tokens }
     * }
     */
    public function analyze(Carbon $since, int $minCount = 5): array
    {
        $actionCount = $this->actions->countUnanalyzed($since);
        $aggregated = $this->actions->aggregateUnanalyzedPatterns($since, $minCount);

        Log::info('feedback.aggregate', [
            'since' => $since->toIso8601String(),
            'min_count' => $minCount,
            'actions_unanalyzed' => $actionCount,
            'pattern_rows' => count($aggregated),
        ]);

        if (empty($aggregated)) {
            return [
                'summary' => [
                    'total_patterns_detected' => 0,
                    'actionable' => 0,
                    'deferred' => 0,
                    'needs_new_skill' => 0,
                ],
                'patterns' => [],
                'deferred' => [],
                'stats' => [
                    'actions_analyzed' => $actionCount,
                    'since' => $since->toIso8601String(),
                    'llm_latency_ms' => 0,
                    'llm_input_tokens' => 0,
                    'llm_output_tokens' => 0,
                ],
            ];
        }

        $promptResult = $this->callLlm($aggregated, $since);

        $parsed = ContentBlocks::extractJson($promptResult['content']);

        return [
            'summary' => $parsed['summary'] ?? [
                'total_patterns_detected' => count($parsed['patterns'] ?? []),
                'actionable' => 0,
                'deferred' => 0,
                'needs_new_skill' => 0,
            ],
            'patterns' => $parsed['patterns'] ?? [],
            'deferred' => $parsed['deferred'] ?? [],
            'stats' => [
                'actions_analyzed' => $actionCount,
                'since' => $since->toIso8601String(),
                'llm_latency_ms' => $promptResult['latency_ms'],
                'llm_input_tokens' => $promptResult['input_tokens'],
                'llm_output_tokens' => $promptResult['output_tokens'],
                'llm_model' => $promptResult['model'],
                'llm_provider' => $this->llm->providerName(),
            ],
        ];
    }

    private function callLlm(array $aggregated, Carbon $since): array
    {
        $userContent = [
            ContentBlocks::text(sprintf(
                "Analyze these %d aggregated KSNB action patterns:\n\n%s\n\nTime range: %s to now.\n\nReturn JSON matching the schema. Output ONLY JSON.",
                count($aggregated),
                json_encode($aggregated, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                $since->toIso8601String(),
            )),
        ];

        $response = $this->llm->messages(
            modelLogicalName: 'judge',
            systemPrompt: $this->systemPrompt(),
            userContent: $userContent,
            maxTokens: 4096,
        );

        Log::info('feedback.llm_call_done', [
            'latency_ms' => $response['latency_ms'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'model' => $response['model'],
        ]);

        return $response;
    }

    /**
     * System prompt từ resources/prompts/feedback_refiner.md (skill output).
     * Inline để service không phụ thuộc filesystem read.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a feedback analysis agent for Baokim OCR pipeline. Your task: read aggregated KSNB user actions on extracted OCR fields, identify failure patterns, and propose targeted updates to existing Claude Skills (.md files) and runtime prompts (resources/prompts/*.md).

CRITICAL CONSTRAINTS:
1. NEVER create new skills. You only refine EXISTING 8 skills:
   - cross-llm-extraction-prompt
   - dev-vision-prompt-designer
   - ocr-document-classifier
   - ocr-extraction-validator
   - ocr-confidence-aggregator
   - vn-pii-masker
   - dev-baokim-sql-reviewer
   - dev-baokim-laravel-repo-pattern
   If pattern is out-of-scope → category="H" + suggested_action="needs_new_skill" + suggest name/scope. Do NOT generate diff for new skills.

2. AH-1: If a pattern is ambiguous/low signal, return suggested_action="needs_more_data" + reasoning. Do NOT invent diffs.

3. AH-4: Every pattern entry MUST have observation + hypothesis (not just diff).

4. Diff format: unified diff in markdown ```diff fenced block.

5. Cap: max 5 patterns per run. Sort by confidence DESC, overflow → deferred array.

6. Output STRICTLY valid JSON. No prose outside JSON.

DECISION TREE:
- A (edit_rate > 70%, cnt >= 10): target dev-vision-prompt-designer + stage2_vision.md, add field validation rule
- B (doc reclassification): target ocr-document-classifier + stage1_classifier.md
- C (mark_wrong cnt >= 5): cat A + raise confidence threshold
- D (validator passes but KSNB edits): target ocr-extraction-validator + Stage3RuleValidator.php
- E (skip cnt >= 20, skip_rate > 80%): drop field from CRITICAL_FIELDS taxonomy
- F (avg_duration > 30s, confidence >= 0.9, cnt >= 5): target ocr-confidence-aggregator + config/ocr.php threshold
- G (PII miss): target vn-pii-masker + Stage4PiiMaskerService.php regex
- H (none above): suggested_action=needs_new_skill, no diff

Output schema:
{
  "summary": {"total_patterns_detected": int, "actionable": int, "deferred": int, "needs_new_skill": int},
  "patterns": [
    {
      "pattern_id": "X-doctype-fieldkey-NNN",
      "category": "A|B|C|D|E|F|G|H",
      "severity": "HIGH|MEDIUM|LOW",
      "observation": "factual 1-2 sentences with numbers",
      "hypothesis": "WHY 1-2 sentences root cause",
      "target_skill_file": "string or null",
      "target_runtime_file": "string or null",
      "proposed_diff": "```diff text``` or null",
      "confidence": 0.0-1.0,
      "suggested_action": "create_pr | needs_more_data | needs_new_skill",
      "suggested_skill_name": "string (only if category H)",
      "suggested_skill_scope": "string (only if category H)"
    }
  ],
  "deferred": [{"pattern_id": "...", "reason": "..."}]
}
PROMPT;
    }

    public function markAnalyzed(Carbon $since): int
    {
        return $this->actions->markBatchAnalyzed($since);
    }
}
