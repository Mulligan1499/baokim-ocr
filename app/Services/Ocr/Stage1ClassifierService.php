<?php

namespace App\Services\Ocr;

use App\Services\Llm\ContentBlocks;
use App\Services\Llm\LlmClient;

class Stage1ClassifierService
{
    public function __construct(private LlmClient $llm) {}

    /**
     * Classify a document file (image or PDF). Provider-agnostic.
     *
     * @return array {
     *   document_detected, document_type, language_detected, image_quality_note,
     *   extraction_strategy_hint, classifier_confidence, _meta: {latency_ms,input_tokens,output_tokens,model,provider}
     * }
     */
    public function classify(string $absoluteFilePath, string $mime): array
    {
        $userContent = [
            ContentBlocks::fileBlock($absoluteFilePath, $mime),
            ContentBlocks::text('Classify this document. Return JSON only.'),
        ];

        $response = $this->llm->messages(
            modelLogicalName: 'classifier',
            systemPrompt: $this->systemPrompt(),
            userContent: $userContent,
            maxTokens: (int) config('ocr.max_tokens.classifier', 600),
        );

        $parsed = ContentBlocks::extractJson($response['content']);
        $parsed['_meta'] = [
            'latency_ms' => $response['latency_ms'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'model' => $response['model'],
            'provider' => $this->llm->providerName(),
        ];

        return $this->normalize($parsed);
    }

    private function normalize(array $data): array
    {
        $allowedTypes = config('ocr.doc_types');
        $type = $data['document_type'] ?? null;
        if ($type !== null && ! in_array($type, $allowedTypes, true)) {
            $type = 'other';
        }
        return [
            'document_detected' => (bool) ($data['document_detected'] ?? false),
            'document_type' => $type,
            'language_detected' => $data['language_detected'] ?? null,
            'image_quality_note' => $data['image_quality_note'] ?? null,
            'extraction_strategy_hint' => $data['extraction_strategy_hint'] ?? null,
            'classifier_confidence' => (float) ($data['classifier_confidence'] ?? 0),
            '_meta' => $data['_meta'] ?? [],
        ];
    }

    private function systemPrompt(): string
    {
        $taxonomy = implode(', ', config('ocr.doc_types'));

        return <<<PROMPT
You are a document classifier for Baokim's KSNB (internal compliance) OCR workflow.
Your task: identify document_type + language + extraction_strategy_hint. Do NOT extract field values.

Output ONLY one JSON object. No commentary outside JSON.

CRITICAL RULES:
1. If image/PDF is not a document → {"document_detected": false}
2. If no class matches → document_type = "other". Conservative bias.
3. Never guess. Output "other"/false rather than fabricate.

Taxonomy (use exactly these values for document_type): {$taxonomy}.
Doc-type meanings:
- cccd: Vietnamese citizen ID card (CCCD/CMND) — Vietnamese government issued, "Căn cước công dân" or "Chứng minh nhân dân" header, 12-digit ID number
- national_id_foreign: National ID card from non-Vietnam country — Chinese 居民身份证, Japanese マイナンバーカード, Korean 주민등록증, etc. Looks like ID card but ID format differs (not 12 digits) and not Vietnamese language. NOT a passport.
- passport: passport booklet (multi-page document with photo + visa pages)
- gpkd: business license / giấy phép kinh doanh
- contract_vi/_en/_zh: contract in that language
- invoice: hóa đơn
- legal_doc: legal document / decision / certificate
- customs_declaration: tờ khai hải quan
- bill_of_lading: vận đơn / B/L
- aml_charter: điều lệ AML
- power_of_attorney: giấy ủy quyền
- labor_contract: hợp đồng lao động
- financial_report: báo cáo tài chính
- other: fallback

Languages: vi, en, zh, mixed, und.
Image-quality may include codes: clear, blurry, image_skewed, low_light, partial_occlusion, wrinkled, low_resolution.

JSON schema:
{
  "document_detected": true|false,
  "document_type": "<one of taxonomy or null>",
  "language_detected": "<vi|en|zh|mixed|und or null>",
  "image_quality_note": "1 sentence",
  "extraction_strategy_hint": "1 sentence — where to focus / translation needed?",
  "classifier_confidence": 0.0-1.0
}

Few-shot examples:

CCCD VN happy path:
{"document_detected":true,"document_type":"cccd","language_detected":"vi","image_quality_note":"Clear front-side Vietnamese CCCD","extraction_strategy_hint":"Header has so_cccd (12 digits), ho_ten, ngay_sinh, ngay_cap","classifier_confidence":0.97}

Chinese national ID (居民身份证):
{"document_detected":true,"document_type":"national_id_foreign","language_detected":"zh","image_quality_note":"Front-side Chinese resident ID card with photo and 18-digit ID number","extraction_strategy_hint":"Extract id_number (18 digits), full_name (Han chars + pinyin), date_of_birth, nationality=China, gender, ethnicity, address","classifier_confidence":0.96}

Ambiguous contract:
{"document_detected":true,"document_type":"contract_vi","language_detected":"vi","image_quality_note":"Vietnamese contract, generic structure","extraction_strategy_hint":"Could be labor_contract if labor-related dominate. Default extract parties+dates+value","classifier_confidence":0.65}

Non-document (anchor — return false instead of guessing):
{"document_detected":false,"document_type":null,"language_detected":null,"image_quality_note":"Landscape photo, not a document","extraction_strategy_hint":null,"classifier_confidence":0.99}
PROMPT;
    }
}
