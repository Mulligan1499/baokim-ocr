<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrDocument extends Model
{
    protected $table = 'ocr_documents';

    protected $fillable = [
        'hash',
        'original_name',
        'mime',
        'size_bytes',
        'storage_path',
        'status',
        'error_message',
        'uploaded_via_api_key_label',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public function extraction()
    {
        return $this->hasOne(OcrExtraction::class, 'document_id');
    }
}
