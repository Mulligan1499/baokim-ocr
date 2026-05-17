<?php

namespace App\Services\Ocr;

use App\Services\Llm\ContentBlocks;
use App\Services\Llm\LlmClient;

/**
 * Stage 3 — Validator. Combines:
 *   3a: Stage3RuleValidator (regex format checks)
 *   3b: Independent LLM-as-judge call (separate from Stage 2 to defeat positive bias)
 *
 * The judge does NOT see the image. It evaluates plausibility of extracted text
 * given doc_type and field-value relationships only.
 */
class Stage3ValidatorService
{
    public function __construct(
        private Stage3RuleValidator $ruleValidator,
        private LlmClient $llm,
    ) {}

    /**
     * @param  array<string,string>  $keyValuesRaw    extracted (key => value)
     * @param  array<string,float>   $stage2SelfConf  per-field self-report from Stage 2
     * @return array {
     *   per_field: array<string,array{rule_passed,rule_reason,judge_confidence,judge_reason,judge_action}>,
     *   _meta: array { judge_latency_ms, judge_input_tokens, judge_output_tokens, judge_model, judge_provider }
     * }
     */
    public function validate(
        array $keyValuesRaw,
        array $stage2SelfConf,
        string $docType,
        ?string $language = 'vi',
    ): array {
        // 3a — Rule-based
        $ruleResults = $this->ruleValidator->validate($keyValuesRaw, $docType);

        // 3b — LLM judge (independent call, NO image — text-only sanity check)
        $judgeResults = $this->callJudge($keyValuesRaw, $stage2SelfConf, $docType, $language);
        $judgeMap = $judgeResults['per_field'];

        $perField = [];
        foreach ($keyValuesRaw as $key => $value) {
            $rule = $ruleResults[$key] ?? ['rule_passed' => true, 'rule_reason' => 'no rule'];
            $judge = $judgeMap[$key] ?? [
                'judge_confidence' => 0.5,
                'judge_reason' => 'no judge entry',
                'judge_action' => 'keep',
            ];
            $perField[$key] = [
                'rule_passed' => (bool) $rule['rule_passed'],
                'rule_reason' => $rule['rule_reason'],
                'judge_confidence' => (float) $judge['judge_confidence'],
                'judge_reason' => $judge['judge_reason'],
                'judge_action' => $judge['judge_action'],
            ];
        }

        return [
            'per_field' => $perField,
            '_meta' => $judgeResults['_meta'],
        ];
    }

    private function callJudge(array $kv, array $selfConf, string $docType, ?string $language): array
    {
        $payloadFields = [];
        foreach ($kv as $key => $value) {
            $payloadFields[] = [
                'key' => $key,
                'value' => $value,
                'stage2_self_confidence' => round((float) ($selfConf[$key] ?? 0), 3),
            ];
        }
        $extractionJson = json_encode($payloadFields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $userContent = [
            ContentBlocks::text(<<<TXT
Document type context: {$docType}
Primary language: {$language}

Extracted key-values to evaluate:
{$extractionJson}

Evaluate each field and return JSON only.
TXT),
        ];

        $response = $this->llm->messages(
            modelLogicalName: 'judge',
            systemPrompt: $this->judgeSystemPrompt(),
            userContent: $userContent,
            maxTokens: 2000,
        );

        $parsed = ContentBlocks::extractJson($response['content']);

        // Build per-field map
        $perField = [];
        foreach ($parsed['evaluations'] ?? [] as $e) {
            $k = $e['key'] ?? null;
            if (! $k) {
                continue;
            }
            $perField[$k] = [
                'judge_confidence' => (float) ($e['judge_confidence'] ?? 0.5),
                'judge_reason' => $e['judge_reason'] ?? '',
                'judge_action' => $e['judge_action'] ?? 'keep',
            ];
        }

        return [
            'per_field' => $perField,
            '_meta' => [
                'judge_latency_ms' => $response['latency_ms'],
                'judge_input_tokens' => $response['input_tokens'],
                'judge_output_tokens' => $response['output_tokens'],
                'judge_model' => $response['model'],
                'judge_provider' => $this->llm->providerName(),
            ],
        ];
    }

    private function judgeSystemPrompt(): string
    {
        return <<<PROMPT
You are an INDEPENDENT validator for OCR extraction output. You do NOT see the original document image — you only see the extracted text values and the document type label.

Your job: sanity check, not re-extraction. For each field, judge if the value is plausible given the doc_type.

CRITICAL CONSTRAINTS:
1. Empty values (value="") are OK and EXPECTED — the extractor opted out. Do NOT penalize. Return judge_confidence=0.5 with reason "empty (extractor opted out)" and action="keep".
2. You cannot verify truth (you don't see the image). You can only flag implausible / wrong-format / inconsistent values.
3. Suspicious patterns to flag:
   - Wrong format for the field type (e.g. CCCD not 12 digits, phone wrong prefix, future dates of birth, amounts that don't make sense)
   - Mismatched semantics (e.g. amount-in-digits vs amount-in-words conflict — if present)
   - Field values that look like OCR misreads (single chars in numeric fields, control chars)
4. Be conservative — when in doubt, judge_confidence ≥ 0.7 and action="keep". Only "flag_low_conf" if you are clearly suspicious.

Return JSON shape:
{
  "evaluations": [
    {
      "key": "<field key>",
      "judge_confidence": 0.0-1.0,
      "judge_reason": "1 short sentence",
      "judge_action": "keep | flag_low_conf | discard"
    }
  ]
}

OUTPUT VALID JSON ONLY. No prose outside JSON.
PROMPT;
    }
}
