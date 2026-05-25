<?php

namespace App\Repositories;

use App\Models\OcrUserAction;
use App\Models\OcrUserActionText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BKM03 — Repository: 1 method = 1 query, no business logic.
 *
 * Sau SCR02.7 split: original_value/final_value/note chuyển sang ocr_user_action_texts.
 * Repository tự tách 2 bảng trong transaction. Caller (Livewire/Service) không thay đổi.
 */
class OcrUserActionRepository
{
    public function create(array $data): OcrUserAction
    {
        return DB::transaction(function () use ($data) {
            $textData = [
                'original_value' => $data['original_value'] ?? null,
                'final_value'    => $data['final_value']    ?? null,
                'note'           => $data['note']           ?? null,
            ];

            unset($data['original_value'], $data['final_value'], $data['note']);

            $action = OcrUserAction::create($data + ['created_at' => now()]);

            if (array_filter($textData, fn ($v) => $v !== null && $v !== '')) {
                OcrUserActionText::create($textData + ['action_id' => $action->id]);
            }

            return $action->load('text');
        });
    }

    /**
     * Xóa action gần nhất (cùng session) cho document + field, dùng cho
     * undo/rollback khi KSNB click nhầm.
     * Chỉ xóa action chưa được analyzed (analyzed_at IS NULL) để bảo toàn audit.
     */
    public function deleteLatestForField(int $documentId, string $fieldKey, string $sessionId): bool
    {
        $latest = OcrUserAction::where('document_id', $documentId)
            ->where('field_key', $fieldKey)
            ->where('session_id', $sessionId)
            ->whereNull('analyzed_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return false;
        }

        // BKM01 no FK → cascade thủ công: xóa text trước rồi xóa action
        OcrUserActionText::where('action_id', $latest->id)->delete();
        return (bool) $latest->delete();
    }

    public function findByDocumentId(int $documentId): Collection
    {
        return OcrUserAction::with('text')
            ->where('document_id', $documentId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Aggregate cho ocr:analyze-actions command:
     * (doc_type, field_key, action_type) → count + edit_rate + avg_duration.
     *
     * BKM02 exception: aggregation query phức tạp dùng query builder.
     * Sau SCR02.7 split: JOIN ocr_user_action_texts để so sánh original vs final.
     */
    public function aggregateUnanalyzedPatterns(Carbon $since, int $minCount = 5): array
    {
        $rows = DB::table('ocr_user_actions as a')
            ->leftJoin('ocr_extractions as e', 'e.document_id', '=', 'a.document_id')
            ->leftJoin('ocr_user_action_texts as t', 't.action_id', '=', 'a.id')
            ->whereNull('a.analyzed_at')
            ->where('a.created_at', '>=', $since)
            ->select([
                'e.doc_type',
                'a.field_key',
                'a.action_type',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('AVG(a.duration_ms) as avg_duration_ms'),
                DB::raw('SUM(CASE WHEN t.original_value <> t.final_value THEN 1 ELSE 0 END) as edited_count'),
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
