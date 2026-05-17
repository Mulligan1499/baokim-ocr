<?php

namespace App\Repositories;

use App\Models\OcrAuditLog;
use Illuminate\Database\Eloquent\Collection;

class OcrAuditLogRepository
{
    public function log(array $data): OcrAuditLog
    {
        return OcrAuditLog::create($data + ['created_at' => now()]);
    }

    public function findByDocumentId(int $documentId): Collection
    {
        return OcrAuditLog::where('document_id', $documentId)
            ->orderBy('id')
            ->get();
    }
}
