<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrAuditLog extends Model
{
    protected $table = 'ocr_audit_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'stage',
        'event_name',
        'payload',
        'latency_ms',
        'tokens_input',
        'tokens_output',
        'claude_model',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'stage' => 'integer',
        'latency_ms' => 'integer',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'created_at' => 'datetime',
    ];
}
