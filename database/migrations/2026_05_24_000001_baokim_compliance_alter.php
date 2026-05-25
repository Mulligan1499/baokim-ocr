<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 — Baokim DB Standards compliance ALTER:
 *  V1 SCR02.4: TIMESTAMP → DATETIME
 *  V2 SCR01.4: rename `event` (reserved word) → `event_name`
 *  V4 SCR05  : created_at NOT NULL DEFAULT CURRENT_TIMESTAMP
 *  V5 IDR01  : index naming prefix idx_/uk_; document_id KEY → UNIQUE KEY
 *  V7 SCR02.6: VARCHAR oversize → đúng size
 *  V9 SCR04  : thêm comment cho cột còn thiếu
 *
 * Idempotent: detect nếu đã apply (event_name tồn tại) → skip.
 * Mục đích: cho phép SQLite test path build schema mới, MySQL prod
 * (đã apply qua SQL script trực tiếp) cũng pass qua migrate command.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotency check — đã apply rồi thì skip toàn bộ
        if (Schema::hasColumn('ocr_audit_logs', 'event_name')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite — column rename only, không hỗ trợ MODIFY type
            Schema::table('ocr_audit_logs', function ($table) {
                $table->renameColumn('event', 'event_name');
            });
            return;
        }

        // MySQL — full ALTER với type + comment
        DB::statement("DELETE e1 FROM ocr_extractions e1 INNER JOIN ocr_extractions e2 ON e1.document_id = e2.document_id AND e1.id < e2.id");

        DB::statement("ALTER TABLE ocr_documents
            MODIFY processed_at DATETIME NULL COMMENT 'Thời điểm pipeline hoàn tất',
            MODIFY cached_until DATETIME NULL COMMENT 'AC-E05 idempotency cache window (24h)',
            MODIFY created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm upload',
            MODIFY updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Cập nhật cuối',
            MODIFY original_name VARCHAR(128) NOT NULL COMMENT 'Tên file gốc',
            MODIFY mime          VARCHAR(50)  NOT NULL COMMENT 'image/jpeg|image/png|image/webp|application/pdf',
            MODIFY storage_path  VARCHAR(255) NOT NULL COMMENT 'Đường dẫn local: ocr/{Y}/{m}/{hash}.{ext}'");

        DB::statement("ALTER TABLE ocr_documents DROP INDEX ocr_documents_hash_unique, ADD UNIQUE KEY uk_hash (hash)");
        DB::statement("ALTER TABLE ocr_documents DROP INDEX ocr_documents_request_id_unique, ADD UNIQUE KEY uk_request_id (request_id)");
        DB::statement("ALTER TABLE ocr_documents DROP INDEX ocr_documents_status_created_at_index, ADD KEY idx_status_created_at (status, created_at)");

        DB::statement("ALTER TABLE ocr_extractions
            MODIFY created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm extraction tạo',
            MODIFY confidence_overall DECIMAL(4,3) NULL COMMENT 'Stage 5 weighted avg, range 0.000-1.000',
            MODIFY requires_review    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=auto-approved, 1=KSNB phải rà soát',
            MODIFY page_count         SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số trang PDF, ảnh = 1'");

        DB::statement("ALTER TABLE ocr_extractions DROP INDEX ocr_extractions_document_id_index, ADD UNIQUE KEY uk_document_id (document_id)");
        DB::statement("ALTER TABLE ocr_extractions DROP INDEX ocr_extractions_quality_created_at_index, ADD KEY idx_quality_created_at (quality, created_at)");
        DB::statement("ALTER TABLE ocr_extractions DROP INDEX ocr_extractions_doc_type_index, ADD KEY idx_doc_type (doc_type)");

        DB::statement("ALTER TABLE ocr_audit_logs
            CHANGE COLUMN event event_name VARCHAR(50) NOT NULL COMMENT 'pipeline_started|classifier_done|extractor_done|validator_done|cross_field_done|pii_masker_done|aggregator_done|extraction_persisted|pipeline_failed|upload_*',
            MODIFY latency_ms    INT UNSIGNED NULL COMMENT 'Độ trễ stage (ms)',
            MODIFY tokens_input  INT UNSIGNED NULL COMMENT 'Input tokens',
            MODIFY tokens_output INT UNSIGNED NULL COMMENT 'Output tokens',
            MODIFY claude_model  VARCHAR(50)  NULL COMMENT 'gemini-2.5-pro | claude-sonnet-4-6'");

        DB::statement("ALTER TABLE ocr_user_actions
            MODIFY created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm KSNB thao tác',
            MODIFY analyzed_at DATETIME NULL COMMENT 'Set bởi ocr:analyze-actions',
            MODIFY field_key   VARCHAR(50) NOT NULL COMMENT 'so_cccd|ho_ten|...',
            MODIFY duration_ms INT UNSIGNED NULL COMMENT 'Thời gian từ page load (ms)'");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ocr_audit_logs', 'event_name')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('ocr_audit_logs', function ($table) {
                $table->renameColumn('event_name', 'event');
            });
            return;
        }

        DB::statement("ALTER TABLE ocr_audit_logs CHANGE COLUMN event_name event VARCHAR(50) NOT NULL");
    }
};
