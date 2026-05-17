<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrUserAction extends Model
{
    protected $table = 'ocr_user_actions';

    public const UPDATED_AT = null;

    public const ACTION_COPY_RAW = 'copy_raw';
    public const ACTION_EDIT_THEN_COPY = 'edit_then_copy';
    public const ACTION_SKIP = 'skip';
    public const ACTION_MARK_WRONG = 'mark_wrong';
    public const ACTION_VIEW_ONLY = 'view_only';
    public const ACTION_OVERALL_COMMENT = 'overall_comment';

    protected $fillable = [
        'document_id',
        'field_key',
        'action_type',
        'original_value',
        'final_value',
        'note',
        'session_id',
        'duration_ms',
        'ksnb_user_label',
        'analyzed_at',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'duration_ms' => 'integer',
        'analyzed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(OcrDocument::class, 'document_id');
    }
}
