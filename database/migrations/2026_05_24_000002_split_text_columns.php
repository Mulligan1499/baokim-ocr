<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 — SCR02.7 compliance: tách cột TEXT/LONGTEXT ra bảng riêng.
 *
 * Lý do: ≥3 cột TEXT/LONGTEXT trong 1 bảng → vi phạm SCR02.7. Mỗi row chứa
 * 3 LONGTEXT có thể >1MB. SELECT không cần text vẫn đọc full row → I/O waste.
 *
 * Tách:
 *  - ocr_extraction_texts (1-1 với ocr_extractions qua extraction_id UK)
 *  - ocr_user_action_texts (1-1 với ocr_user_actions qua action_id UK)
 *
 * Idempotent: detect ocr_extraction_texts đã tồn tại → skip.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotency check
        if (Schema::hasTable('ocr_extraction_texts')) {
            return;
        }

        // ────────────────────────────────────────────────────────────
        // Bảng ocr_extraction_texts
        // ────────────────────────────────────────────────────────────
        Schema::create('ocr_extraction_texts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('extraction_id')->comment('BKM01 no FK — soft ref ocr_extractions.id');
            $table->longText('text_full')->nullable()->comment('RAW text — KSNB copy-paste (chứa PII)');
            $table->longText('text_full_masked')->nullable()->comment('PII-masked cho audit/export');
            $table->longText('translation_vi')->nullable()->comment('Bản dịch tiếng Việt full document');
            $table->dateTime('created_at')->useCurrent()->comment('Thời điểm text tạo');

            $table->unique('extraction_id', 'uk_extraction_id');
        });

        // Migrate data (nếu cột text_full vẫn còn trong ocr_extractions)
        if (Schema::hasColumn('ocr_extractions', 'text_full')) {
            DB::statement("
                INSERT INTO ocr_extraction_texts
                    (extraction_id, text_full, text_full_masked, translation_vi, created_at)
                SELECT id, text_full, text_full_masked, translation_vi, COALESCE(created_at, CURRENT_TIMESTAMP)
                FROM ocr_extractions
                WHERE text_full IS NOT NULL OR text_full_masked IS NOT NULL OR translation_vi IS NOT NULL
            ");

            Schema::table('ocr_extractions', function (Blueprint $table) {
                $table->dropColumn(['text_full', 'text_full_masked', 'translation_vi']);
            });
        }

        // ────────────────────────────────────────────────────────────
        // Bảng ocr_user_action_texts
        // ────────────────────────────────────────────────────────────
        Schema::create('ocr_user_action_texts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('action_id')->comment('BKM01 no FK — soft ref ocr_user_actions.id');
            $table->text('original_value')->nullable()->comment('AI extracted (Stage 2 raw)');
            $table->text('final_value')->nullable()->comment('KSNB final sau khi edit');
            $table->text('note')->nullable()->comment('Free-text feedback từ KSNB');
            $table->dateTime('created_at')->useCurrent()->comment('Thời điểm text tạo');

            $table->unique('action_id', 'uk_action_id');
        });

        // Migrate data (nếu cột original_value vẫn còn trong ocr_user_actions)
        if (Schema::hasColumn('ocr_user_actions', 'original_value')) {
            DB::statement("
                INSERT INTO ocr_user_action_texts
                    (action_id, original_value, final_value, note, created_at)
                SELECT id, original_value, final_value, note, COALESCE(created_at, CURRENT_TIMESTAMP)
                FROM ocr_user_actions
                WHERE original_value IS NOT NULL OR final_value IS NOT NULL OR note IS NOT NULL
            ");

            Schema::table('ocr_user_actions', function (Blueprint $table) {
                $table->dropColumn(['original_value', 'final_value', 'note']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ocr_extraction_texts')) {
            return;
        }

        // Restore cột vào ocr_extractions
        if (! Schema::hasColumn('ocr_extractions', 'text_full')) {
            Schema::table('ocr_extractions', function (Blueprint $table) {
                $table->longText('text_full')->nullable()->after('page_count');
                $table->longText('text_full_masked')->nullable()->after('text_full');
                $table->longText('translation_vi')->nullable()->after('key_values_masked');
            });

            DB::statement("
                UPDATE ocr_extractions e
                INNER JOIN ocr_extraction_texts t ON t.extraction_id = e.id
                SET e.text_full = t.text_full,
                    e.text_full_masked = t.text_full_masked,
                    e.translation_vi = t.translation_vi
            ");
        }

        Schema::dropIfExists('ocr_extraction_texts');

        // Restore cột vào ocr_user_actions
        if (! Schema::hasColumn('ocr_user_actions', 'original_value')) {
            Schema::table('ocr_user_actions', function (Blueprint $table) {
                $table->text('original_value')->nullable();
                $table->text('final_value')->nullable();
                $table->text('note')->nullable();
            });

            DB::statement("
                UPDATE ocr_user_actions a
                INNER JOIN ocr_user_action_texts t ON t.action_id = a.id
                SET a.original_value = t.original_value,
                    a.final_value = t.final_value,
                    a.note = t.note
            ");
        }

        Schema::dropIfExists('ocr_user_action_texts');
    }
};
