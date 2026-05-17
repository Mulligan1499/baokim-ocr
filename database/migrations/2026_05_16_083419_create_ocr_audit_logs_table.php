<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // BKM06: partition by month for log table.
            DB::statement(<<<'SQL'
                CREATE TABLE ocr_audit_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    document_id BIGINT UNSIGNED NOT NULL,
                    stage TINYINT UNSIGNED NOT NULL COMMENT '0..6 harness stage',
                    event VARCHAR(50) NOT NULL,
                    payload JSON NULL COMMENT 'masked snapshot in/out',
                    latency_ms INT UNSIGNED NULL,
                    tokens_input INT UNSIGNED NULL,
                    tokens_output INT UNSIGNED NULL,
                    claude_model VARCHAR(50) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id, created_at),
                    KEY idx_document_id (document_id),
                    KEY idx_stage (stage)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                PARTITION BY RANGE (TO_DAYS(created_at)) (
                    PARTITION p202605 VALUES LESS THAN (TO_DAYS('2026-06-01')),
                    PARTITION p202606 VALUES LESS THAN (TO_DAYS('2026-07-01')),
                    PARTITION p202607 VALUES LESS THAN (TO_DAYS('2026-08-01')),
                    PARTITION p202608 VALUES LESS THAN (TO_DAYS('2026-09-01')),
                    PARTITION p202609 VALUES LESS THAN (TO_DAYS('2026-10-01')),
                    PARTITION p202610 VALUES LESS THAN (TO_DAYS('2026-11-01')),
                    PARTITION p202611 VALUES LESS THAN (TO_DAYS('2026-12-01')),
                    PARTITION p202612 VALUES LESS THAN (TO_DAYS('2027-01-01')),
                    PARTITION p202701 VALUES LESS THAN (TO_DAYS('2027-02-01')),
                    PARTITION p202702 VALUES LESS THAN (TO_DAYS('2027-03-01')),
                    PARTITION pmax VALUES LESS THAN MAXVALUE
                )
            SQL);
            return;
        }

        // Non-MySQL (e.g. sqlite for testing) — same columns, no partition.
        Schema::create('ocr_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedTinyInteger('stage');
            $table->string('event', 50);
            $table->json('payload')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->string('claude_model', 50)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('document_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_audit_logs');
    }
};
