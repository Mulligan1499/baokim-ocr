<?php

namespace App\Repositories;

use App\Models\OcrDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OcrDocumentRepository
{
    public function findById(int $id): ?OcrDocument
    {
        return OcrDocument::find($id);
    }

    public function findByHash(string $hash): ?OcrDocument
    {
        return OcrDocument::where('hash', $hash)->first();
    }

    public function create(array $data): OcrDocument
    {
        return OcrDocument::create($data);
    }

    public function updateStatus(int $id, string $status, ?string $errorMessage = null): void
    {
        OcrDocument::where('id', $id)->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = OcrDocument::query()->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }
}
