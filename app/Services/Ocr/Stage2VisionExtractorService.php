<?php

namespace App\Services\Ocr;

use App\Services\Llm\ContentBlocks;
use App\Services\Llm\LlmClient;

class Stage2VisionExtractorService
{
    /**
     * Critical fields per doc_type (default from stage2_vision.md).
     */
    private const CRITICAL_FIELDS = [
        'cccd' => ['so_cccd', 'ho_ten', 'ngay_sinh', 'ngay_cap'],
        'passport' => ['passport_number', 'full_name', 'date_of_birth', 'expiry_date', 'nationality'],
        'gpkd' => ['mst', 'ten_doanh_nghiep', 'dia_chi', 'nguoi_dai_dien', 'ngay_cap'],
        'contract_vi' => ['ben_a', 'ben_b', 'ngay_ky', 'gia_tri'],
        'contract_en' => ['party_a', 'party_b', 'signing_date', 'value'],
        'contract_zh' => ['party_a', 'party_b', 'signing_date', 'value'],
        'invoice' => ['invoice_number', 'total_amount', 'invoice_date', 'mst'],
        'legal_doc' => ['document_number', 'issuing_authority', 'issue_date'],
        'customs_declaration' => ['declaration_number', 'declaration_date', 'importer', 'exporter', 'hs_code'],
        'bill_of_lading' => ['bl_number', 'shipper', 'consignee', 'vessel_name', 'container_number'],
        'aml_charter' => ['document_number', 'effective_date', 'issuing_authority'],
        'power_of_attorney' => ['principal', 'attorney', 'scope', 'effective_date', 'expiry_date'],
        'labor_contract' => ['employer', 'employee', 'position', 'salary', 'signing_date'],
        'financial_report' => ['reporting_period', 'total_revenue', 'net_profit', 'total_assets', 'currency'],
        'other' => [],
    ];

    public function __construct(private LlmClient $llm) {}

    /**
     * Extract a document. Returns parsed structure + _meta.
     */
    public function extract(
        string $absoluteFilePath,
        string $mime,
        string $docType,
        string $languageHint,
        ?string $strategyHint = null,
    ): array {
        $criticalFields = self::CRITICAL_FIELDS[$docType] ?? [];
        $translateToVi = $languageHint !== 'vi' && $languageHint !== 'und';

        $systemPrompt = $this->systemPrompt($docType, $languageHint, $criticalFields, $translateToVi, $strategyHint);

        $userContent = [
            ContentBlocks::fileBlock($absoluteFilePath, $mime),
            ContentBlocks::text(
                'Extract this document per the system instructions. Return ONLY valid JSON matching the schema.'
            ),
        ];

        $response = $this->llm->messages(
            modelLogicalName: 'extractor',
            systemPrompt: $systemPrompt,
            userContent: $userContent,
            maxTokens: 8000,
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
        $kvRaw = $data['key_values'] ?? [];
        $kvMap = [];
        $confPerField = [];
        $warnings = $data['warnings_self_report'] ?? [];

        foreach ($kvRaw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? null;
            if (! $key) {
                continue;
            }
            $kvMap[$key] = [
                'value' => $entry['value'] ?? '',
                'value_translated_vi' => $entry['value_translated_vi'] ?? null,
                'critical' => (bool) ($entry['critical'] ?? false),
                'flagged_low_confidence' => (bool) ($entry['flagged_low_confidence'] ?? false),
                'validation_passed' => (bool) ($entry['validation_passed'] ?? true),
            ];
            $confPerField[$key] = (float) ($entry['confidence'] ?? 0);

            if (! empty($entry['flagged_low_confidence'])) {
                $warnings[] = [
                    'field' => $key,
                    'code' => 'low_confidence',
                    'msg' => 'Self-flagged by extractor',
                ];
            }
        }

        return [
            'document_detected' => (bool) ($data['document_detected'] ?? false),
            'reason_if_not_detected' => $data['reason_if_not_detected'] ?? null,
            'document_type_actual' => $data['document_type_actual'] ?? null,
            'language_detected' => $data['language_detected'] ?? null,
            'image_quality_note' => $data['image_quality_note'] ?? null,
            'raw_text' => $data['raw_text'] ?? '',
            'translation_vi' => $data['translation_vi'] ?? null,
            'key_values' => $kvMap,
            'confidence_per_field' => $confPerField,
            'overall_confidence_self_report' => (float) ($data['overall_confidence_self_report'] ?? 0),
            'warnings_self_report' => $warnings,
            '_meta' => $data['_meta'] ?? [],
        ];
    }

    private function systemPrompt(
        string $docType,
        string $languageHint,
        array $criticalFields,
        bool $translateToVi,
        ?string $strategyHint,
    ): string {
        $criticalList = $criticalFields ? implode(', ', $criticalFields) : '(none)';
        $translateFlag = $translateToVi ? 'true' : 'false';
        $strategy = $strategyHint ?? '(no hint)';

        return <<<PROMPT
You are an OCR + structured information extractor for Baokim's KSNB (internal compliance) onboarding workflow.

Your task: extract raw text + key-values from a document (image or PDF). The result is consumed by Vietnamese compliance staff who copy-paste fields into internal systems. Accuracy and refusal-when-unsure outrank completeness.

OUTPUT: a single JSON object matching the schema below. No commentary outside JSON.

============================================================
CRITICAL RULES (in priority order)
============================================================
1. If the input is NOT a document → return {"document_detected": false, "reason_if_not_detected": "..."}.
2. If you cannot read a value clearly → value="" and confidence=0. DO NOT guess, interpolate, or invent.
3. Per-field confidence ∈ [0,1] calibrated as below.
4. Critical fields failing format validation → flagged_low_confidence=true, validation_passed=false. Do NOT auto-correct.
5. Translation: only translate text you actually extracted. Numbers/IDs/codes stay as-is. Chinese names → original characters + Hán-Việt phonetic in value_translated_vi.
6. Image-quality codes for warnings_self_report:
   blurry | image_skewed | low_light | partial_occlusion | wrinkled | low_resolution | watermark_obscures_text
7. Output STRICTLY valid JSON. No prose outside JSON.

============================================================
Confidence calibration
============================================================
- 0.90-1.00 : fully visible, unambiguous
- 0.70-0.90 : visible but ambiguous chars (0/O, 1/l, B/8)
- 0.50-0.70 : partially visible
- 0.30-0.50 : mostly obscured — strongly consider value=""
- 0.00-0.30 : illegible — return value=""

============================================================
Field-specific validation rules
============================================================
- so_cccd / cccd_number : exactly 12 digits
- passport_number       : 1 letter + 7-8 digits typical
- mst                   : 10 or 13 digits
- phone_vn              : 10 digits starting 03|05|07|08|09
- email                 : has @ and .
- dates                 : DD/MM/YYYY, valid calendar date, not in future
- amounts               : numeric with currency unit

============================================================
THIS CALL CONTEXT
============================================================
Expected document type: {$docType}
Expected primary language: {$languageHint}
Translate text fields to Vietnamese: {$translateFlag}
Critical fields (weight 2): {$criticalList}
Stage 1 strategy hint: {$strategy}

============================================================
Workflow
============================================================
1. In `image_quality_note` (1-2 sentences) describe doc type observed + image quality + any obstructions.
2. Extract `raw_text` — full visible text, preserve line breaks; use ⟦unreadable⟧ for unreadable parts.
3. Build `key_values[]` — for each expected critical/normal field emit an entry. If field not visible emit value="", confidence=0.
4. Compute `overall_confidence_self_report` ∈ [0,1].
5. `warnings_self_report`: array using codes above.
6. If translate=true: provide `translation_vi` (full Vietnamese translation of raw_text) and `value_translated_vi` per text field.

JSON schema:
{
  "document_detected": true,
  "reason_if_not_detected": "",
  "document_type_actual": "<one of taxonomy or 'other'>",
  "language_detected": "vi|en|zh|mixed|und",
  "image_quality_note": "1-2 sentences",
  "raw_text": "...",
  "translation_vi": "full Vietnamese translation when translate=true and language!=vi, else null",
  "key_values": [
    {"key":"so_cccd","value":"001234567890","value_translated_vi":null,"confidence":0.95,"critical":true,"flagged_low_confidence":false,"validation_passed":true}
  ],
  "overall_confidence_self_report": 0.0-1.0,
  "warnings_self_report": []
}

ANCHOR examples (learn from these):

Happy CCCD VN:
{"document_detected":true,"document_type_actual":"cccd","language_detected":"vi","image_quality_note":"Clear front-side Vietnamese CCCD","raw_text":"...","translation_vi":null,"key_values":[{"key":"so_cccd","value":"001234567890","confidence":0.97,"critical":true,"validation_passed":true,"flagged_low_confidence":false,"value_translated_vi":null}],"overall_confidence_self_report":0.94,"warnings_self_report":[]}

NEGATIVE — smudged CCCD (return "" instead of guessing):
{"document_detected":true,"document_type_actual":"cccd","language_detected":"vi","image_quality_note":"CCCD with smudge over digits 4-8 of id_number","raw_text":"...Số: 0012⟦unreadable⟧7890...","translation_vi":null,"key_values":[{"key":"so_cccd","value":"","confidence":0.0,"critical":true,"validation_passed":false,"flagged_low_confidence":true,"value_translated_vi":null},{"key":"ho_ten","value":"NGUYỄN VĂN A","confidence":0.92,"critical":true,"validation_passed":true,"flagged_low_confidence":false,"value_translated_vi":null}],"overall_confidence_self_report":0.55,"warnings_self_report":["partial_occlusion"]}

NON-DOCUMENT (refusal anchor):
{"document_detected":false,"reason_if_not_detected":"Image shows a landscape photo of mountains; no document content.","document_type_actual":null,"language_detected":null,"image_quality_note":"Outdoor photograph","raw_text":"","translation_vi":null,"key_values":[],"overall_confidence_self_report":0.0,"warnings_self_report":[]}
PROMPT;
    }
}
