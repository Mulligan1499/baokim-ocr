<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OcrExtraction
 */
class ExtractionResource extends JsonResource
{
    private string $view = 'raw';

    public function withView(string $view): self
    {
        $this->view = $view === 'masked' ? 'masked' : 'raw';
        return $this;
    }

    public function toArray(Request $request): array
    {
        $useMasked = $this->view === 'masked';
        $stageMeta = $this->stage_metadata ?? [];

        return [
            'doc_type' => $this->doc_type,
            'language_detected' => $this->language_detected,
            'page_count' => $this->page_count,
            'text_full' => $useMasked ? $this->text_full_masked : $this->text_full,
            'key_values' => $useMasked ? ($this->key_values_masked ?? []) : ($this->key_values ?? []),
            'translation_vi' => $this->translation_vi,
            'confidence_overall' => $this->confidence_overall,
            'confidence_per_field' => $this->confidence_per_field ?? new \stdClass(),
            'quality' => $this->quality,
            'requires_review' => (bool) $this->requires_review,
            'warnings' => $this->warnings ?? [],
            'review_priority_fields' => $stageMeta['stage5_review_priority'] ?? [],
            'view_mode' => $this->view,
        ];
    }
}
