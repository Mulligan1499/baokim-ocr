<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tách từ ocr_user_actions theo SCR02.7 — ≥3 TEXT phải tách bảng.
 * 1-1 với ocr_user_actions qua action_id (UNIQUE KEY).
 */
class OcrUserActionText extends Model
{
    protected $table = 'ocr_user_action_texts';

    public const UPDATED_AT = null;

    protected $fillable = [
        'action_id',
        'original_value',
        'final_value',
        'note',
    ];

    public function action()
    {
        return $this->belongsTo(OcrUserAction::class, 'action_id');
    }
}
