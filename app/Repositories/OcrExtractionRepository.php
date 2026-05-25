<?php

namespace App\Repositories;

use App\Models\OcrExtraction;
use App\Models\OcrExtractionText;
use Illuminate\Support\Facades\DB;

/**
 * BKM03 — Repository: 1 function = 1 query (hoặc 1 transaction tách bảng cha-con).
 *
 * Sau SCR02.7 split: text_full/text_full_masked/translation_vi đã chuyển sang
 * bảng ocr_extraction_texts. Repository nhận array shape cũ (có text fields),
 * tự tách sang 2 bảng trong 1 transaction.
 */
class OcrExtractionRepository
{
    public function findByDocumentId(int $documentId): ?OcrExtraction
    {
        return OcrExtraction::with('text')
            ->where('document_id', $documentId)
            ->first();
    }

    public function create(array $data): OcrExtraction
    {
        return DB::transaction(function () use ($data) {
            $textData = [
                'text_full'        => $data['text_full']        ?? null,
                'text_full_masked' => $data['text_full_masked'] ?? null,
                'translation_vi'   => $data['translation_vi']   ?? null,
            ];

            unset($data['text_full'], $data['text_full_masked'], $data['translation_vi']);

            $extraction = OcrExtraction::create($data);

            if (array_filter($textData, fn ($v) => $v !== null && $v !== '')) {
                OcrExtractionText::create($textData + ['extraction_id' => $extraction->id]);
            }

            return $extraction->load('text');
        });
    }

    public function deleteByDocumentId(int $documentId): void
    {
        $extractionIds = OcrExtraction::where('document_id', $documentId)->pluck('id');
        if ($extractionIds->isEmpty()) {
            return;
        }

        OcrExtractionText::whereIn('extraction_id', $extractionIds)->delete();
        OcrExtraction::whereIn('id', $extractionIds)->delete();
    }
}
