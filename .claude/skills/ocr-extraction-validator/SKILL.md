---
name: ocr-extraction-validator
description: |
  Skill thiết kế Stage 3 Validator trong harness 7 stages của OCR Baokim. Stage 3 chia 2 sub-stage độc lập với Stage 2 (Generator): 3a là rule-based programmatic (regex CCCD/passport/MST/phone/email/date/amount), 3b là LLM-as-judge — separate Claude API call đánh giá output Stage 2 (KHÔNG để Stage 2 tự chấm mình, chống bias dương). Đầu vào: key_values output từ Stage 2 + doc_type context. Đầu ra: validated_key_values với adjusted confidence + reasoning per field + validation_passed flag + System prompt cho 3b LLM judge + PHP service skeleton Stage3ValidatorService. Sit on top of cross-llm-extraction-prompt. Generic enough cho mọi LLM extraction pipeline cần independent validation.
  Triggers: "Stage 3 validator", "validate extraction output LLM", "rule-based validator + LLM judge", "anti-bias dương cho LLM extraction", "design Stage3ValidatorService", "validation logic cho CCCD/MST/phone/date", "extraction validator separate model call".
  Anti-triggers: schema validation kiểu Laravel Form Request (đó là Stage 0), data validation cho user input frontend, integration testing LLM (dùng QA skill), confidence aggregation (đó là Stage 5).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: dev
created: 2026-05-15
updated: 2026-05-15
tags: [dev, ocr, validation, llm-judge, anti-hallucination, stage-3, baokim]
---

# OCR Extraction Validator (Stage 3)

## Mục đích

Stage 3 trong harness 7 stages (file 02 Section 4). 2 sub-stage độc lập với Stage 2:

- **3a — Rule-based**: programmatic validation (regex, format check) — không tốn LLM call
- **3b — LLM-as-judge**: SEPARATE Claude call đánh giá Stage 2 output → chống bias dương (Stage 2 không tự chấm mình)

**Core design principle**: Stage 3 phải độc lập với Stage 2 ở mọi nghĩa — separate model call, separate prompt, separate context. Nếu để Stage 2 self-judge → confidence inflate, anti-hallucination yếu.

**Inherit từ**: `cross-llm-extraction-prompt` (7 patterns AH)
**Reference**: file 02 Section 4 Stage 3, Section 10 (Confidence strategy)

## Khi nào dùng

✅ Build Stage 3 cho project OCR Baokim
✅ Design validator cho LLM extraction pipeline khác (CV parsing, contract extraction)
✅ Adapt rule-based validator cho domain mới (thêm pattern field)
✅ Code `Stage3ValidatorService` Laravel

❌ Form request validation Laravel (Stage 0 hoặc validation request rules)
❌ Integration test LLM accuracy (QA skill)
❌ Aggregate confidence multi-signal (Stage 5)
❌ Validate schema structure (json schema lib, không cần LLM)

## Workflow

### Step 1: Identify field validation rules

Per critical field, define:

| Field type | Rule 3a (programmatic) | Cross-check |
|---|---|---|
| CCCD 12 số | regex `^\d{12}$` | First 3 digits = province code valid |
| Passport VN | regex `^[A-Z]\d{7}$` | Letter prefix valid (B, C, N...) |
| MST 10 số | regex `^\d{10}(-\d{3})?$` | Checksum digit 10 valid |
| Phone VN | regex `^0[35789]\d{8}$` | Carrier prefix valid |
| Email | regex RFC 5322 simplified | Domain reachable (optional) |
| Date | parse + valid calendar | Not future, not before 1900 |
| Amount | parse number + currency | Match amount-in-words if both present |
| Account number | 10-14 digits | Bank code prefix valid (optional) |

### Step 2: Design 3a rule-based validator

PHP service `RuleBasedValidator` — không cần LLM:

```php
namespace App\Services\Ocr\Validation;

class RuleBasedValidator
{
    public function validate(array $keyValues, string $docType): array
    {
        $results = [];
        foreach ($keyValues as $kv) {
            $results[] = [
                'key' => $kv['key'],
                'rule_passed' => $this->checkRule($kv['key'], $kv['value']),
                'rule_confidence_adjustment' => 0,  // -0.3 if fail
            ];
        }
        return $results;
    }

    private function checkRule(string $key, string $value): bool
    {
        return match (true) {
            in_array($key, ['id_number', 'cccd']) => $this->validateCccd($value),
            $key === 'passport_number' => $this->validatePassport($value),
            $key === 'tax_code' => $this->validateMst($value),
            str_contains($key, 'phone') => $this->validatePhoneVn($value),
            str_contains($key, 'email') => $this->validateEmail($value),
            str_contains($key, 'date') => $this->validateDate($value),
            str_contains($key, 'amount') => $this->validateAmount($value),
            default => true,  // unknown field type → skip (pass)
        };
    }

    private function validateCccd(string $v): bool
    {
        return $v === '' || preg_match('/^\d{12}$/', $v);
        // Empty is fine (extracted as "" means low confidence already)
    }
    // ... other validators
}
```

**Quan trọng**: Empty value (`value=""`) → KHÔNG fail rule. Stage 2 đã trả empty vì không đọc được — đó là correct behavior, không phải lỗi.

### Step 3: Design 3b LLM-as-judge prompt

System Prompt cho separate Claude call:

```
You are an independent validator for OCR extraction output.
Your task: evaluate if the extracted key-value pairs are reasonable, given the document type.

CRITICAL CONSTRAINTS:
1. You do NOT see the original image. You see only the extraction output.
2. Your job is sanity check, not re-extraction.
3. If extraction looks suspicious (wrong format, impossible value, inconsistent with doc type) → flag.
4. Empty values (value="") are OK — that means extractor couldn't read. Do NOT penalize.

Document type: {doc_type}
Expected fields for this doc type: {expected_fields}
Extracted data:
{key_values_json}

For each field, return:
- field_key
- judge_confidence: 0-1, how confident you are extraction is correct given context
- reasoning: 1 sentence why
- suggested_action: "keep" | "flag_low_conf" | "discard"

CRITICAL: You assess plausibility, not truth. You cannot see the image.
```

→ Patterns AH-1 (empty không guess) + AH-4 (reasoning trace) inherit.

### Step 4: Aggregate output

Output schema cho downstream Stage 5:

```json
{
  "validation_results": [
    {
      "key": "id_number",
      "value": "001234567890",
      "stage2_self_confidence": 0.97,
      "stage3a_rule_passed": true,
      "stage3a_rule_adjustment": 0,
      "stage3b_judge_confidence": 0.95,
      "stage3b_reasoning": "12-digit Vietnamese CCCD format valid, consistent with id_card doc type",
      "stage3b_suggested_action": "keep",
      "flagged_low_confidence": false
    }
  ],
  "overall_validation_passed": true,
  "warnings_added": []
}
```

## PHP Service skeleton

```php
<?php
namespace App\Services\Ocr;

use App\Services\Ocr\Validation\RuleBasedValidator;
use Anthropic\Client as AnthropicClient;
use App\Repositories\OcrVendorCallRepository;

class Stage3ValidatorService
{
    public function __construct(
        private RuleBasedValidator $ruleValidator,
        private AnthropicClient $claude,
        private OcrVendorCallRepository $vendorCallRepo,
    ) {}

    public function validate(array $stage2Output, string $docType, string $requestId): array
    {
        // 3a: Rule-based (cheap, fast)
        $ruleResults = $this->ruleValidator->validate(
            $stage2Output['key_values'],
            $docType
        );

        // 3b: LLM judge — SEPARATE call (anti-bias)
        $judgeResults = $this->callLlmJudge($stage2Output, $docType, $requestId);

        // Merge results
        return $this->mergeValidationResults($stage2Output, $ruleResults, $judgeResults);
    }

    private function callLlmJudge(array $stage2Output, string $docType, string $requestId): array
    {
        $startMs = microtime(true);
        
        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-6-20260217',  // Sonnet OK cho judge, accuracy matter
            'max_tokens' => 2000,
            'system' => $this->buildJudgePrompt($docType, $stage2Output['key_values']),
            'messages' => [[
                'role' => 'user',
                'content' => 'Evaluate the extraction. Return JSON only.',
            ]],
        ]);

        $this->vendorCallRepo->logCall([
            'request_id' => $requestId,
            'stage' => 3,  // 3b
            'model' => 'claude-sonnet-4-6-20260217',
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'latency_ms' => (int)((microtime(true) - $startMs) * 1000),
        ]);

        return json_decode($response->content[0]->text, true);
    }

    private function mergeValidationResults(array $stage2, array $rule, array $judge): array
    {
        // Combine self-report + rule + judge per field
        // ... (see Output schema above)
    }
}
```

## Examples

### Example 1: CCCD validation (happy path)

**Input** từ Stage 2:
```json
{"key": "id_number", "value": "001234567890", "confidence": 0.97}
```

**3a output**: rule_passed=true (12 digits)
**3b output**: judge_confidence=0.95, reasoning="Valid 12-digit CCCD format", action="keep"
**Final**: flagged=false, all 3 signals agree

### Example 2: CCCD invalid format

**Input** từ Stage 2:
```json
{"key": "id_number", "value": "0012345", "confidence": 0.85}
```

**3a output**: rule_passed=false (chỉ 7 digits, fail regex)
**3b output**: judge_confidence=0.3, reasoning="Only 7 digits, CCCD must be 12. Likely OCR misread.", action="flag_low_conf"
**Final**: flagged_low_confidence=true, confidence cap at 0.4 (forced low per Section 10)

### Example 3: Anti-bias dương — Stage 2 over-confident

**Input** từ Stage 2:
```json
{"key": "total_amount", "value": "1500000", "confidence": 0.95}
```

Nhưng context: invoice có "Một triệu rưỡi đồng" written in words = 1,500,000 ✓

**3a output**: amount format valid
**3b output**: judge_confidence=0.97 ("Amount matches words"), action="keep"
**Final**: High confidence preserved

VS scenario invoice có "Một triệu năm trăm nghìn" (1,500,000) nhưng value="15000000" (extra zero):

**3a output**: amount format valid (just number)
**3b output**: judge_confidence=0.4 ("Amount 15M conflicts with words 1.5M"), action="flag_low_conf"
**Final**: flagged_low_confidence=true even though 3a passed

→ Đây là điểm 3b LLM-judge thêm value: cross-check semantic ngoài regex.

### Example 4: Reusability — adapt cho CV parsing

**Input**: CV extraction output từ ba-cv-parser (TBD)
```json
{"key": "email", "value": "tran.b@example.com", "confidence": 0.99}
{"key": "phone", "value": "0987654321", "confidence": 0.95}
{"key": "years_experience", "value": "5", "confidence": 0.85}
```

**3a output**: 
- email: regex pass
- phone: phone VN format pass
- years_experience: integer 0-50 pass

**3b output**: judge confirms plausibility

→ Skill reusable. Chỉ thêm validator rules trong RuleBasedValidator.

## What NOT to do

❌ KHÔNG để Stage 2 model tự chấm Stage 2 output (same Claude call) — bias dương
❌ KHÔNG dùng cùng prompt cho 3b judge và Stage 2 — judge cần prompt độc lập
❌ KHÔNG penalize empty values — Stage 2 trả `""` là correct anti-hallucination behavior
❌ KHÔNG auto-correct value trong Stage 3 — chỉ flag. Correction là responsibility Stage 5 hoặc human review
❌ KHÔNG bypass 3a chạy thẳng 3b — 3a free + catch 80% lỗi format, 3b catch semantic
❌ KHÔNG dùng Haiku cho 3b — accuracy matter, dùng Sonnet
❌ KHÔNG ship Stage 3 thiếu reasoning field — debugging mù mịt khi confidence low

## Reusability angle

Generic structure cho bất kỳ LLM extraction pipeline:
- Email triage: validate intent enum, urgency level
- CV parsing: validate email/phone, years_experience range, education enum
- Contract extraction: validate party names not empty, dates valid, contract_value > 0
- Customer feedback: validate sentiment enum, category enum

Skill này foundation cho independent validator pattern.

## Reference foundation

- `cross-llm-extraction-prompt`: 7 patterns AH (inherit)
- `dev-baokim-laravel-repo-pattern`: PHP service skeleton
- file 02 Section 4 Stage 3: design rationale
- file 02 Section 10: confidence aggregation downstream

## Maintenance & Roadmap

- v0.2.0: tách `references/validation-rules/` cho per-field-type rules
- v0.3.0: thêm validators specific industry (bằng lái xe, BHXH)
- v1.0.0 (active): tested 500+ extractions, ROC AUC ≥ 0.85

Tests: `tests/prompts.md`
