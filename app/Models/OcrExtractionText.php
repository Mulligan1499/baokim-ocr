<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tách từ ocr_extractions theo SCR02.7 — ≥3 LONGTEXT phải tách bảng.
 * 1-1 với ocr_extractions qua extraction_id (UNIQUE KEY).
 */
class OcrExtractionText extends Model
{
    protected $table = 'ocr_extraction_texts';

    public const UPDATED_AT = null;

    protected $fillable = [
        'extraction_id',
        'text_full',
        'text_full_masked',
        'translation_vi',
    ];

    public function extraction()
    {
        return $this->belongsTo(OcrExtraction::class, 'extraction_id');
    }
}
