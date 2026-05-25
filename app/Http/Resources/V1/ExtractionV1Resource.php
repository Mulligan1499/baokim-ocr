<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AC contract — schema BA yêu cầu trong AC-01..04, AC-AI-01..05.
 *
 * @mixin \App\Models\OcrExtraction
 */
class ExtractionV1Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\OcrDocument $doc */
        $doc = $this->resource->document ?? null;

        $stageMeta = $this->stage_metadata ?? [];
        $stage1Meta = $stageMeta['stage1'] ?? [];
        $stage2Meta = $stageMeta['stage2_meta'] ?? [];

        $classifierConfidence = (float) ($stage1Meta['classifier_confidence'] ?? 0);
        $unknownThreshold = (float) config('ocr.classifier_unknown_threshold', 0.5);

        $documentType = $this->doc_type ?: 'unknown';
        if ($classifierConfidence < $unknownThreshold) {
            $documentType = 'unknown';
        }

        $languageDetected = $this->mapLanguage($this->language_detected);

        $keyValuesRaw = $this->key_values ?? [];
        $confPerField = $this->confidence_per_field ?? [];
        $kvPairs = $this->buildKeyValuePairs($keyValuesRaw, $confPerField);

        $missingFields = [];
        $anyFlagged = false;
        foreach ($kvPairs as $pair) {
            if ($pair['flagged'] ?? false) {
                $anyFlagged = true;
                if (($pair['value'] ?? '') === '') {
                    $missingFields[] = 'missing_field:' . $pair['key'];
                }
            }
        }

        $warnings = $this->buildWarnings($this->warnings ?? [], $missingFields, $languageDetected);

        // R4: requires_review=true nếu có ≥ 1 field flagged (KSNB phải xem lại field đó)
        // Hoặc nếu Stage 5 đã set requires_review (overall quality thấp)
        $requiresReview = (bool) $this->requires_review || $anyFlagged;

        return [
            'request_id' => $doc?->request_id,
            'raw_text' => $this->text_full ?? '',
            'key_value_pairs' => $kvPairs,
            'language_detected' => $languageDetected,
            'translation_vi' => $languageDetected === 'vi' ? null : $this->translation_vi,
            'overall_confidence' => (float) $this->confidence_overall,
            'document_type' => $documentType,
            'page_count' => (int) ($this->page_count ?? 1),
            'processed_at' => optional($doc?->processed_at ?? $this->created_at)->toIso8601String(),
            'ai_model_version' => $stage2Meta['model'] ?? null,
            'warnings' => $warnings,
            'quality' => $this->quality,
            'requires_review' => $requiresReview,
        ];
    }

    private function mapLanguage(?string $internal): string
    {
        if ($internal === null || $internal === 'und') {
            return 'other';
        }
        if (in_array($internal, ['vi', 'en', 'zh'], true)) {
            return $internal;
        }
        if ($internal === 'mixed') {
            return 'mixed';
        }
        return 'other';
    }

    /**
     * Transform internal {key: {value, confidence, ...}} → AC array [{key, value, confidence, ...}].
     */
    private function buildKeyValuePairs(array $keyValuesRaw, array $confPerField): array
    {
        // R4: ≥ 0.7 không flag; < 0.7 flagged để KSNB review thủ công.
        $flagBelow = (float) config('ocr.confidence_thresholds.flag_below', 0.70);

        $out = [];
        foreach ($keyValuesRaw as $key => $entry) {
            if (! is_array($entry)) {
                $confidence = (float) ($confPerField[$key] ?? 0);
                $out[] = [
                    'key' => $key,
                    'value' => (string) $entry,
                    'confidence' => $confidence,
                    'flagged' => $confidence < $flagBelow,
                    'value_translated_vi' => null,
                    'name_phonetic_vi' => null,
                ];
                continue;
            }

            $value = $entry['value'] ?? '';
            $confidence = (float) ($confPerField[$key] ?? $entry['confidence'] ?? 0);
            $flagged = (bool) ($entry['flagged_low_confidence'] ?? false)
                || $confidence < $flagBelow;

            $translatedVi = $entry['value_translated_vi'] ?? null;

            // AC-04: tên người Trung phiên âm Hán-Việt → sub-field name_phonetic_vi.
            // Detect CJK characters trong value gốc → expose phonetic riêng.
            $phoneticVi = null;
            if ($translatedVi !== null && $this->containsCjk($value)) {
                $phoneticVi = $translatedVi;
            }

            $out[] = [
                'key' => $key,
                'value' => $value,
                'confidence' => $confidence,
                'flagged' => $flagged,
                'value_translated_vi' => $translatedVi,
                'name_phonetic_vi' => $phoneticVi,
            ];
        }
        return $out;
    }

    private function containsCjk(string $value): bool
    {
        // CJK Unified Ideographs (basic) + Extension A — bao đủ Hán + tên người
        return (bool) preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $value);
    }

    private function buildWarnings(array $existing, array $missingFields, string $language): array
    {
        $out = [];
        foreach ($existing as $w) {
            if (is_string($w)) {
                $out[] = $w;
                continue;
            }
            if (is_array($w)) {
                $code = $w['code'] ?? null;
                $field = $w['field'] ?? null;
                if ($code) {
                    $out[] = $field && $field !== '_global' ? "{$code}:{$field}" : $code;
                }
            }
        }
        $out = array_merge($out, $missingFields);

        if ($language === 'other') {
            $out[] = 'LANGUAGE_OUT_OF_SCOPE';
        } elseif ($language === 'mixed') {
            $out[] = 'MIXED_LANGUAGE';
        }

        return array_values(array_unique($out));
    }
}
