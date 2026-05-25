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

    public function findByRequestId(string $requestId): ?OcrDocument
    {
        return OcrDocument::where('request_id', $requestId)->first();
    }

    public function findByHash(string $hash): ?OcrDocument
    {
        return OcrDocument::where('hash', $hash)->first();
    }

    /**
     * AC-E05: tìm 1 document cached còn hiệu lực (24h) cho cùng hash.
     */
    public function findCachedByHash(string $hash): ?OcrDocument
    {
        return OcrDocument::where('hash', $hash)
            ->where('status', OcrDocument::STATUS_DONE)
            ->where('cached_until', '>', now())
            ->first();
    }

    public function create(array $data): OcrDocument
    {
        return OcrDocument::create($data);
    }

    public function markProcessedAt(int $id, int $cacheHours): void
    {
        OcrDocument::where('id', $id)->update([
            'processed_at' => now(),
            'cached_until' => now()->addHours($cacheHours),
        ]);
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

    /**
     * AC-06 list history theo limit/offset (BA gọi qua API v1).
     *
     * @return array{items: \Illuminate\Support\Collection<OcrDocument>, total: int}
     */
    public function listForHistory(int $limit, int $offset, array $filters = []): array
    {
        $query = OcrDocument::with('extraction')->orderByDesc('id');

        foreach (['status', 'request_id'] as $col) {
            if (! empty($filters[$col])) {
                $query->where($col, $filters[$col]);
            }
        }

        $total = (clone $query)->count();
        $items = $query->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }
}
