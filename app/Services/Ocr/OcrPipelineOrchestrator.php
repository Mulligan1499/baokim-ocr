<?php

namespace App\Services\Ocr;

use App\Models\OcrDocument;
use App\Repositories\OcrAuditLogRepository;
use App\Repositories\OcrDocumentRepository;
use App\Repositories\OcrExtractionRepository;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Support\Facades\Log;
use Throwable;

class OcrPipelineOrchestrator
{
    public function __construct(
        private OcrDocumentRepository $documents,
        private OcrExtractionRepository $extractions,
        private OcrAuditLogRepository $audit,
        private DocumentStorageService $storage,
        private Stage1ClassifierService $stage1,
        private Stage2VisionExtractorService $stage2,
        private Stage3ValidatorService $stage3,
        private Stage3CrossFieldValidator $stage3c,
        private Stage4PiiMaskerService $stage4,
        private Stage5ConfidenceAggregator $stage5,
        private PdfPageCounter $pageCounter,
    ) {}

    public function run(int $documentId): void
    {
        // Pipeline 3 LLM calls có thể mất 30-45s. Override PHP timeout cho process
        // hiện tại (artisan serve sub-process không nhận -d max_execution_time).
        @set_time_limit(0);

        $doc = $this->documents->findById($documentId);
        if (! $doc) {
            return;
        }

        $this->documents->updateStatus($doc->id, OcrDocument::STATUS_PROCESSING);
        $this->audit->log([
            'document_id' => $doc->id,
            'stage' => 0,
            'event_name' => 'pipeline_started',
            'payload' => ['mime' => $doc->mime, 'size_bytes' => $doc->size_bytes],
        ]);
        Log::info('ocr.pipeline.started', [
            'document_id' => $doc->id,
            'mime' => $doc->mime,
            'size_bytes' => $doc->size_bytes,
        ]);

        try {
            $absolutePath = $this->storage->absolutePath($doc->storage_path);

            // ---- Stage 0.5: page count enforcement (AC-02 + cost guard) ----
            $pageCount = $this->pageCounter->count($absolutePath, $doc->mime);
            $maxPages = (int) config('ocr.max_pages', 20);
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 0,
                'event_name' => 'page_count_checked',
                'payload' => ['page_count' => $pageCount, 'max_pages' => $maxPages],
            ]);

            if ($pageCount > $maxPages) {
                $this->documents->updateStatus(
                    $doc->id,
                    OcrDocument::STATUS_FAILED,
                    "PDF có {$pageCount} trang vượt giới hạn {$maxPages} trang. Vui lòng tách file.",
                );
                return;
            }

            // ---- Stage 1 ---- Classifier
            $cls = $this->stage1->classify($absolutePath, $doc->mime);
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 1,
                'event_name' => 'classifier_done',
                'payload' => [
                    'document_type' => $cls['document_type'],
                    'language' => $cls['language_detected'],
                    'classifier_confidence' => $cls['classifier_confidence'],
                    'image_quality_note' => $cls['image_quality_note'],
                ],
                'latency_ms' => $cls['_meta']['latency_ms'] ?? null,
                'tokens_input' => $cls['_meta']['input_tokens'] ?? null,
                'tokens_output' => $cls['_meta']['output_tokens'] ?? null,
                'claude_model' => $cls['_meta']['model'] ?? null,
            ]);

            if (! $cls['document_detected']) {
                $this->documents->updateStatus(
                    $doc->id,
                    OcrDocument::STATUS_FAILED,
                    'Not a document: ' . ($cls['image_quality_note'] ?? 'unknown'),
                );
                return;
            }

            $docType = $cls['document_type'] ?: 'other';
            $language = $cls['language_detected'] ?: 'und';

            // ---- Stage 2 ---- Vision extraction
            $ext = $this->stage2->extract(
                absoluteFilePath: $absolutePath,
                mime: $doc->mime,
                docType: $docType,
                languageHint: $language,
                strategyHint: $cls['extraction_strategy_hint'],
            );
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 2,
                'event_name' => 'extractor_done',
                'payload' => [
                    'document_type_actual' => $ext['document_type_actual'],
                    'language' => $ext['language_detected'],
                    'self_confidence' => $ext['overall_confidence_self_report'],
                    'key_count' => count($ext['key_values'] ?? []),
                    'has_translation' => $ext['translation_vi'] !== null,
                ],
                'latency_ms' => $ext['_meta']['latency_ms'] ?? null,
                'tokens_input' => $ext['_meta']['input_tokens'] ?? null,
                'tokens_output' => $ext['_meta']['output_tokens'] ?? null,
                'claude_model' => $ext['_meta']['model'] ?? null,
            ]);

            if (! $ext['document_detected']) {
                $this->documents->updateStatus(
                    $doc->id,
                    OcrDocument::STATUS_FAILED,
                    'Extractor declined: ' . ($ext['reason_if_not_detected'] ?? 'unknown'),
                );
                return;
            }

            $keyValuesRaw = [];
            $stage2SelfConf = $ext['confidence_per_field'] ?? [];
            foreach ($ext['key_values'] as $key => $entry) {
                $keyValuesRaw[$key] = $entry['value'];
            }

            // ---- Stage 3 ---- Validator (rule + LLM judge)
            $val = $this->stage3->validate(
                keyValuesRaw: $keyValuesRaw,
                stage2SelfConf: $stage2SelfConf,
                docType: $ext['document_type_actual'] ?? $docType,
                language: $ext['language_detected'] ?? $language,
            );
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 3,
                'event_name' => 'validator_done',
                'payload' => [
                    'rule_pass_count' => count(array_filter($val['per_field'], fn ($f) => $f['rule_passed'])),
                    'rule_fail_count' => count(array_filter($val['per_field'], fn ($f) => ! $f['rule_passed'])),
                    'judge_action_summary' => $this->summarizeActions($val['per_field']),
                ],
                'latency_ms' => $val['_meta']['judge_latency_ms'] ?? null,
                'tokens_input' => $val['_meta']['judge_input_tokens'] ?? null,
                'tokens_output' => $val['_meta']['judge_output_tokens'] ?? null,
                'claude_model' => $val['_meta']['judge_model'] ?? null,
            ]);

            // ---- Stage 3c ---- Cross-field logic validator (deterministic, no LLM)
            $crossField = $this->stage3c->validate(
                $keyValuesRaw,
                $ext['document_type_actual'] ?? $docType,
            );
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 3,
                'event_name' => 'cross_field_done',
                'payload' => [
                    'has_logic_error' => $crossField['has_logic_error'],
                    'warning_count' => count($crossField['warnings']),
                    'codes' => array_column($crossField['warnings'], 'code'),
                ],
            ]);

            // ---- Stage 4 ---- PII masker (runs on RAW text + raw key_values)
            $textMasked = $this->stage4->maskText($ext['raw_text']);
            $kvMasked = $this->stage4->maskKeyValues($keyValuesRaw);
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 4,
                'event_name' => 'pii_masker_done',
                'payload' => [
                    'text_pii_detected' => $textMasked['detected'],
                    'kv_pii_detected' => $kvMasked['detected'],
                ],
            ]);

            // ---- Stage 5 ---- Confidence aggregator
            $criticalFields = $this->getCriticalFields($ext['document_type_actual'] ?? $docType);
            $agg = $this->stage5->aggregate(
                keyValuesRaw: $keyValuesRaw,
                stage2SelfConf: $stage2SelfConf,
                stage3PerField: $val['per_field'],
                criticalFieldKeys: $criticalFields,
                imageQualityNote: $cls['image_quality_note'] ?? $ext['image_quality_note'] ?? null,
            );

            // Merge Stage 3c cross-field warnings + force requires_review nếu có logic error
            foreach ($crossField['warnings'] as $w) {
                $agg['warnings'][] = [
                    'field' => $w['fields'][0] ?? '_global',
                    'code' => $w['code'],
                    'msg' => $w['msg'],
                ];
            }
            if ($crossField['has_logic_error']) {
                $agg['requires_review'] = true;
                // Hạ overall_confidence 15% (penalty cứng cho logic mismatch)
                $agg['overall_confidence'] = round($agg['overall_confidence'] * 0.85, 3);
                if ($agg['quality'] === 'high') {
                    $agg['quality'] = 'medium';
                }
            }

            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 5,
                'event_name' => 'aggregator_done',
                'payload' => [
                    'overall_confidence' => $agg['overall_confidence'],
                    'quality' => $agg['quality'],
                    'requires_review' => $agg['requires_review'],
                    'warning_count' => count($agg['warnings']),
                    'cross_field_logic_error' => $crossField['has_logic_error'],
                ],
            ]);

            // ---- Stage 6 ---- Persist
            $this->extractions->deleteByDocumentId($doc->id);
            $this->extractions->create([
                'document_id' => $doc->id,
                'language_detected' => $ext['language_detected'] ?? $language,
                'doc_type' => $ext['document_type_actual'] ?? $docType,
                'page_count' => $pageCount,
                'text_full' => $ext['raw_text'],
                'text_full_masked' => $textMasked['masked'],
                // Persist NESTED Stage 2 output (value + value_translated_vi + critical + flagged + validation_passed)
                // V1 Resource cần shape này để expose per-field translation + flagged. Livewire UI handle cả 2 shape.
                'key_values' => $ext['key_values'],
                'key_values_masked' => $kvMasked['masked_kv'],
                'translation_vi' => $ext['translation_vi'],
                'confidence_overall' => $agg['overall_confidence'],
                'confidence_per_field' => $agg['confidence_per_field'],
                'quality' => $agg['quality'],
                'requires_review' => $agg['requires_review'],
                'warnings' => $agg['warnings'],
                'stage_metadata' => [
                    'stage1' => $cls,
                    'stage2_meta' => $ext['_meta'] ?? [],
                    'stage3_per_field' => $val['per_field'],
                    'stage5_aggregated' => $agg['aggregated'],
                    'stage5_review_priority' => $agg['review_priority_fields'],
                    'pii_detected_in_text' => $textMasked['detected'],
                    'pii_detected_in_kv' => $kvMasked['detected'],
                ],
            ]);

            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 6,
                'event_name' => 'extraction_persisted',
                'payload' => [
                    'quality' => $agg['quality'],
                    'requires_review' => $agg['requires_review'],
                    'confidence_overall' => $agg['overall_confidence'],
                ],
            ]);

            $this->documents->updateStatus($doc->id, OcrDocument::STATUS_DONE);
            $this->documents->markProcessedAt(
                $doc->id,
                (int) config('ocr.idempotency_window_hours', 24),
            );
            Log::info('ocr.pipeline.done', [
                'document_id' => $doc->id,
                'doc_type' => $ext['document_type_actual'] ?? $docType,
                'language' => $ext['language_detected'] ?? $language,
                'overall_confidence' => $agg['overall_confidence'],
                'quality' => $agg['quality'],
                'requires_review' => $agg['requires_review'],
                'warning_count' => count($agg['warnings']),
            ]);
        } catch (Throwable $e) {
            Log::error('OCR pipeline failed', [
                'document_id' => $doc->id,
                'exception' => class_basename($e),
                'message' => $e->getMessage(),
            ]);
            $this->audit->log([
                'document_id' => $doc->id,
                'stage' => 1,
                'event_name' => 'pipeline_failed',
                'payload' => [
                    'exception' => class_basename($e),
                    'message' => substr($e->getMessage(), 0, 500),
                ],
            ]);
            $this->documents->updateStatus(
                $doc->id,
                OcrDocument::STATUS_FAILED,
                substr($e->getMessage(), 0, 1000),
            );
            throw $e;
        }
    }

    private function summarizeActions(array $perField): array
    {
        $summary = ['keep' => 0, 'flag_low_conf' => 0, 'discard' => 0];
        foreach ($perField as $f) {
            $action = $f['judge_action'] ?? 'keep';
            $summary[$action] = ($summary[$action] ?? 0) + 1;
        }
        return $summary;
    }

    private function getCriticalFields(string $docType): array
    {
        // Đọc từ config/ocr_doc_taxonomy.php — single source of truth.
        // Stage 2 service cũng đọc cùng config để critical fields đồng bộ.
        return config("ocr_doc_taxonomy.{$docType}.critical", []);
    }
}
