---
name: ocr-document-classifier
description: |
  Skill thiết kế prompt cho Stage 1 Document Classifier trong harness 7 stages của dự án OCR Baokim KSNB. Stage 1 chạy trên Claude Haiku 4.5 (rẻ + nhanh) để classify document type + language detection + extraction strategy hint, trước khi handoff sang Stage 2 Vision Extractor. Đầu vào: file ảnh/PDF đã pass Stage 0 validation + taxonomy reference (default 13 KSNB doc types). Đầu ra: System prompt + User prompt template + JSON schema output + 3-4 few-shot examples cover happy path + ambiguous case + non-document refusal + PHP service code skeleton cho Stage1ClassifierService. Sit on top of cross-llm-extraction-prompt foundation, inherit 7 anti-hallucination patterns.
  Triggers: "Stage 1 classifier prompt", "document type classification prompt", "phân loại tài liệu OCR", "Haiku prompt cho classify", "design Stage1ClassifierService", "language detection prompt", "extraction strategy hint".
  Anti-triggers: full extraction prompt (dùng dev-vision-prompt-designer cho Stage 2), classifier cho non-document (audio/video), generic ML classification (không phải LLM), classify với mô hình tự train (Tesseract + custom model).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: dev
created: 2026-05-15
updated: 2026-05-15
tags: [dev, ocr, classification, vision, haiku, stage-1, baokim]
---

# OCR Document Classifier (Stage 1)

## Mục đích

Skill thiết kế prompt cho Stage 1 trong harness 7 stages (file 02 Section 4):
- **Model**: Claude Haiku 4.5 (cost ~$0.0005/call, nhanh)
- **Mục đích**: classify trước khi Stage 2 extract chi tiết → tăng accuracy + giảm cost (Stage 2 với hint chính xác extract nhanh hơn)
- **Output**: document_type, language_detected, extraction_strategy hint

**Inherit từ**: `cross-llm-extraction-prompt` (7 patterns AH)
**Reference**: file 02 Section 4 (Stage 1), Section 5 (13 doc types)

## Khi nào dùng skill này

✅ Build prompt classifier cho dự án OCR KSNB
✅ Adapt classifier cho domain khác (HR document, Pháp chế document) — đổi taxonomy
✅ Optimize classifier để giảm cost (Haiku thay vì Sonnet)
✅ Code `Stage1ClassifierService` Laravel

❌ Build prompt extract chi tiết (dùng `dev-vision-prompt-designer`)
❌ Generic ML classification (Tesseract, custom CNN)
❌ Classify text (không phải image) — adapt skill nhưng đổi model

## Workflow

### Step 1: Define classification contract

3 thông tin cần xác định:

1. **Taxonomy**: list các class enum + 1 fallback `other`/`unknown`
   - Default cho project: 13 KSNB doc types từ file 02 Section 5
   - Adapt cho HR: cv, application_letter, employment_contract, evaluation_form
2. **Language scope**: list ngôn ngữ support (KSNB project: vi/en/zh/mixed/other)
3. **Strategy hints**: gợi ý cho Stage 2 — focus area, language-specific handling

### Step 2: Apply patterns AH từ foundation

Inherit từ `cross-llm-extraction-prompt`:
- AH-1: Empty thay vì guess → document_type="other" thay vì bịa class
- AH-3: Refusal → non-document return `document_detected=false`
- AH-4: Reasoning trace → "image shows [description]"
- AH-7: Negative few-shot → 1 example trả `other` hoặc refusal

Classifier-specific:
- **Single output**: chỉ 1 document_type, không multi-label (trừ trường hợp explicit ambiguous)
- **Conservative bias**: khi không chắc → trả `other` thay vì guess class gần nhất
- **Language detection threshold**: > 60% chars 1 ngôn ngữ → đó là primary; ngược lại → `mixed`

### Step 3: Generate Haiku-optimized prompt

Khác Sonnet, Haiku optimize tốt cho:
- Prompt ngắn (system prompt < 1000 tokens)
- Output JSON đơn giản (không nested deep)
- Few-shot ít hơn (2-3 examples đủ thay vì 5)

### Step 4: Generate PHP service skeleton

Output `Stage1ClassifierService.php` theo BKM03 pattern (inherit từ `dev-baokim-laravel-repo-pattern`).

## Output structure

### System Prompt template

```
You are a document classifier for Baokim's KSNB OCR workflow.
Your task: identify document type + language + extraction strategy, NOT extract details.

Output ONE JSON matching the schema. Do not extract fields — that's a later stage.

CRITICAL RULES:
1. If image is not a document → return {"document_detected": false}
2. If document doesn't match any class → return document_type="other"
3. Never guess. Conservative bias: prefer "other" over wrong class.

Classes (taxonomy):
{taxonomy_list}

Languages: vi, en, zh, mixed, other
Strategy hint: 1-sentence guidance for next stage (extraction focus area).

Workflow:
1. Glance at image: type + quality (image_quality_note, 1 sentence)
2. Match against taxonomy
3. Detect primary language
4. Suggest extraction strategy
```

### User Prompt template

```
[Image attached]

Classify this document. Return JSON only.
```

### Output Schema (compact, Haiku-friendly)

```json
{
  "document_detected": true,
  "document_type": "id_card",
  "language_detected": "vi",
  "image_quality_note": "Clear front-side CCCD",
  "extraction_strategy_hint": "Focus on header section: id_number, name, dob, issue date",
  "classifier_confidence": 0.95
}
```

### PHP Service skeleton

```php
<?php
namespace App\Services\Ocr;

use App\Repositories\OcrVendorCallRepository;
use Anthropic\Client as AnthropicClient;

class Stage1ClassifierService
{
    public function __construct(
        private AnthropicClient $claude,
        private OcrVendorCallRepository $vendorCallRepo,
    ) {}

    /**
     * Classify document. Cheap call, Haiku.
     */
    public function classify(string $imagePathOrBase64, string $requestId): array
    {
        $startMs = microtime(true);
        
        $response = $this->claude->messages()->create([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 500,
            'system' => $this->buildSystemPrompt(),
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => $this->prepareImage($imagePathOrBase64)],
                    ['type' => 'text', 'text' => 'Classify this document. Return JSON only.'],
                ],
            ]],
        ]);

        $latencyMs = (int)((microtime(true) - $startMs) * 1000);
        
        // Audit (BKM06)
        $this->vendorCallRepo->logCall([
            'request_id' => $requestId,
            'stage' => 1,
            'vendor' => 'anthropic',
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'latency_ms' => $latencyMs,
            'status' => 0, // ok
        ]);

        return json_decode($response->content[0]->text, true);
    }

    private function buildSystemPrompt(): string
    {
        // Build from taxonomy + language scope
        // ... (see Templates section)
    }

    private function prepareImage(string $input): array
    {
        // base64 encode if file path, else pass through
        // ...
    }
}
```

## Examples

### Example 1: Happy path — CCCD VN

**Input**: Ảnh CCCD VN rõ nét, mặt trước

**Expected Haiku output**:
```json
{
  "document_detected": true,
  "document_type": "id_card",
  "language_detected": "vi",
  "image_quality_note": "Clear front-side Vietnamese CCCD",
  "extraction_strategy_hint": "Header section has id_number (12 digits), full_name, dob, issue_date. Confidence: high.",
  "classifier_confidence": 0.97
}
```

### Example 2: Multi-language — Vận đơn tiếng Trung mixed English

**Input**: Bill of Lading có cả tiếng Trung và English headers

**Expected output**:
```json
{
  "document_detected": true,
  "document_type": "bill_of_lading",
  "language_detected": "mixed",
  "image_quality_note": "B/L document with bilingual zh/en sections",
  "extraction_strategy_hint": "Translate Chinese sections to vi. Numbers/codes (bl_number, container) stay as-is. Critical: shipper, consignee.",
  "classifier_confidence": 0.92
}
```

### Example 3: Ambiguous — Hợp đồng general

**Input**: Hợp đồng có thể là contract_vi hoặc employment_contract

**Expected output**:
```json
{
  "document_detected": true,
  "document_type": "contract_vi",
  "language_detected": "vi",
  "image_quality_note": "Vietnamese contract, generic structure, parties + terms visible",
  "extraction_strategy_hint": "If contains 'người sử dụng lao động' → re-classify as employment_contract. Default: extract parties, dates, value.",
  "classifier_confidence": 0.68
}
```

→ Conservative: trả contract_vi (broader) thay vì cố đoán employment_contract.

### Example 4: Non-document (refusal, AH-3 + AH-7)

**Input**: Ảnh phong cảnh

**Expected output**:
```json
{
  "document_detected": false,
  "document_type": null,
  "language_detected": null,
  "image_quality_note": "Image shows landscape with mountains, not a document",
  "extraction_strategy_hint": null,
  "classifier_confidence": 0.99
}
```

→ Negative few-shot bắt buộc trong prompt (AH-7).

## What NOT to do

❌ KHÔNG extract fields trong Stage 1 — đó là việc Stage 2. Skill này CHỈ classify.
❌ KHÔNG dùng Sonnet 4.6 cho Stage 1 — waste cost. Haiku đủ chính xác cho classification task.
❌ KHÔNG return multi-label trừ khi user explicit cho phép — conservative single-label
❌ KHÔNG bịa class khi unsure — trả `other`
❌ KHÔNG hardcode 13 KSNB taxonomy inline trong prompt — pass qua variable để adapt cho domain khác
❌ KHÔNG skip negative few-shot (AH-7) — Haiku dễ hallucinate hơn Sonnet nếu thiếu

## Reusability angle

Adapt cho domain khác chỉ cần đổi taxonomy:

**HR taxonomy**: cv, application_letter, employment_contract, evaluation_form, leave_request, salary_slip
**Pháp chế taxonomy**: nda, service_contract, lease_agreement, power_of_attorney, court_judgment
**Sales taxonomy**: proposal, quotation, customer_feedback, complaint_letter, lead_form

Pass taxonomy + strategy hints khác — cùng skill, cùng workflow, cùng output schema.

## Maintenance & Roadmap

- v0.2.0: tách `references/taxonomies/` cho mỗi domain (ksnb, hr, legal, sales)
- v0.3.0: ensemble classifier (chạy 2 Haiku call → vote) cho ambiguous cases
- v1.0.0 (active): 100+ classification runs, accuracy ≥ 90%

Tests: `tests/prompts.md`
