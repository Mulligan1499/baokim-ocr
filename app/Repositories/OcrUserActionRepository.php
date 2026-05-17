<?php

namespace App\Repositories;

use App\Models\OcrUserAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BKM03 — 1 method = 1 query, no business logic.
 */
class OcrUserActionRepository
{
    public function create(array $data): OcrUserAction
    {
        return OcrUserAction::create($data + ['created_at' => now()]);
    }

    public function findByDocumentId(int $documentId): Collection
    {
        return OcrUserAction::where('document_id', $documentId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Aggregate cho ocr:analyze-actions command:
     * (doc_type, field_key, action_type) → count + edit_rate + avg_duration.
     *
     * Join ocr_extractions để có doc_type (BKM01 no FK nhưng JOIN OK qua document_id).
     */
    public function aggregateUnanalyzedPatterns(Carbon $since, int $minCount = 5): array
    {
        $rows = DB::table('ocr_user_actions as a')
            ->leftJoin('ocr_extractions as e', 'e.document_id', '=', 'a.document_id')
            ->whereNull('a.analyzed_at')
            ->where('a.created_at', '>=', $since)
            ->select([
                'e.doc_type',
                'a.field_key',
                'a.action_type',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('AVG(a.duration_ms) as avg_duration_ms'),
                DB::raw('SUM(CASE WHEN a.original_value <> a.final_value THEN 1 ELSE 0 END) as edited_count'),
            ])
            ->groupBy('e.doc_type', 'a.field_key', 'a.action_type')
            ->having('cnt', '>=', $minCount)
            ->orderByDesc('cnt')
            ->get();

        return $rows->map(fn ($r) => (array) $r)->all();
    }

    public function markBatchAnalyzed(Carbon $since): int
    {
        return OcrUserAction::whereNull('analyzed_at')
            ->where('created_at', '>=', $since)
            ->update(['analyzed_at' => now()]);
    }

    public function countUnanalyzed(Carbon $since): int
    {
        return OcrUserAction::whereNull('analyzed_at')
            ->where('created_at', '>=', $since)
            ->count();
    }
}
