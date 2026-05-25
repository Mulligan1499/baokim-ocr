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
        // 3a — Rule-based (luôn chạy, deterministic)
        $ruleResults = $this->ruleValidator->validate($keyValuesRaw, $docType);

        // 3b — LLM judge (skip nếu doc type có pipeline_skip = ['stage3b'])
        $skipStages = config("ocr_doc_taxonomy.{$docType}.pipeline_skip", []);
        $skipJudge = in_array('stage3b', $skipStages, true);

        if ($skipJudge) {
            // Fallback: dùng stage2_self_conf làm judge_confidence để Stage 5 vẫn tính weight được
            $judgeMap = [];
            foreach ($keyValuesRaw as $key => $_) {
                $judgeMap[$key] = [
                    'judge_confidence' => (float) ($stage2SelfConf[$key] ?? 0.7),
                    'judge_reason' => 'Bỏ qua LLM judge (loại tài liệu đơn giản — Stage 3a đủ).',
                    'judge_action' => 'keep',
                ];
            }
            $judgeMeta = [
                'judge_latency_ms' => 0,
                'judge_input_tokens' => 0,
                'judge_output_tokens' => 0,
                'judge_model' => 'skipped',
                'judge_provider' => 'none',
                'skipped' => true,
            ];
        } else {
            $judgeResults = $this->callJudge($keyValuesRaw, $stage2SelfConf, $docType, $language);
            $judgeMap = $judgeResults['per_field'];
            $judgeMeta = $judgeResults['_meta'];
        }

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
            '_meta' => $judgeMeta,
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
            maxTokens: (int) config('ocr.max_tokens.judge', 4000),
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
      "judge_reason": "1 short sentence IN VIETNAMESE",
      "judge_action": "keep | flag_low_conf | discard"
    }
  ]
}

CRITICAL LANGUAGE RULE:
- The `judge_reason` field MUST be written in **VIETNAMESE** (tiếng Việt), regardless of document language.
- This message will be shown directly to Vietnamese KSNB compliance staff.
- Be concise: 1 short sentence, max 15 words.
- Examples of correct Vietnamese reasons:
  - "Định dạng MST không chuẩn — VN MST phải có 10 hoặc 13 chữ số."
  - "Số CCCD đủ 12 chữ số, hợp lệ."
  - "Trống — extractor không đọc được, giữ nguyên."
  - "Ngày sinh nghi vấn — năm trong tương lai."
  - "Số tiền không có đơn vị tiền tệ."

OUTPUT VALID JSON ONLY. No prose outside JSON.
PROMPT;
    }
}
