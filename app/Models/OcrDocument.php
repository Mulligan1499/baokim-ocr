<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OcrDocument extends Model
{
    protected $table = 'ocr_documents';

    protected $fillable = [
        'request_id',
        'hash',
        'original_name',
        'mime',
        'size_bytes',
        'storage_path',
        'status',
        'error_message',
        'uploaded_via_api_key_label',
        'processed_at',
        'cached_until',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'cached_until' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::creating(function (OcrDocument $doc) {
            if (! $doc->request_id) {
                $doc->request_id = (string) Str::uuid();
            }
        });
    }

    public function extraction()
    {
        return $this->hasOne(OcrExtraction::class, 'document_id');
    }
}
