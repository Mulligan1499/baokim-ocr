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
        'text_full',
        'text_full_masked',
        'key_values',
        'key_values_masked',
        'translation_vi',
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
}
