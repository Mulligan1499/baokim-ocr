<?php

namespace App\Repositories;

use App\Models\OcrExtraction;

class OcrExtractionRepository
{
    public function findByDocumentId(int $documentId): ?OcrExtraction
    {
        return OcrExtraction::where('document_id', $documentId)->first();
    }

    public function create(array $data): OcrExtraction
    {
        return OcrExtraction::create($data);
    }

    public function deleteByDocumentId(int $documentId): void
    {
        OcrExtraction::where('document_id', $documentId)->delete();
    }
}
