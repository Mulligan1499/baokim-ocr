<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrExtraction extends Model
{
    protected $table = 'ocr_extractions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'language_detected',
        'doc_type',
        'page_count',
        'key_values',
        'key_values_masked',
        'confidence_overall',
        'confidence_per_field',
        'quality',
        'requires_review',
        'warnings',
        'stage_metadata',
    ];

    protected $casts = [
        'key_values' => 'array',
        'key_values_masked' => 'array',
        'confidence_per_field' => 'array',
        'warnings' => 'array',
        'stage_metadata' => 'array',
        'confidence_overall' => 'float',
        'requires_review' => 'boolean',
        'page_count' => 'integer',
    ];

    public function document()
    {
        return $this->belongsTo(OcrDocument::class, 'document_id');
    }

    public function text()
    {
        return $this->hasOne(OcrExtractionText::class, 'extraction_id');
    }

    public function getTextFullAttribute(): ?string
    {
        return $this->text?->text_full;
    }

    public function getTextFullMaskedAttribute(): ?string
    {
        return $this->text?->text_full_masked;
    }

    public function getTranslationViAttribute(): ?string
    {
        return $this->text?->translation_vi;
    }
}
