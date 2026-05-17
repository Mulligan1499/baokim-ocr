<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CHAR(4) không đủ cho "mixed" (5 chars) hoặc future codes.
        // Expand sang VARCHAR(10) — fit mọi code + reserve room.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ocr_extractions MODIFY language_detected VARCHAR(10) NULL COMMENT 'vi|en|zh|mixed|und'");
            return;
        }

        // SQLite (test) hoặc khác: dùng Schema builder (Laravel xử lý column re-create)
        Schema::table('ocr_extractions', function (Blueprint $table) {
            $table->string('language_detected', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ocr_extractions MODIFY language_detected CHAR(4) NULL");
            return;
        }

        Schema::table('ocr_extractions', function (Blueprint $table) {
            $table->char('language_detected', 4)->nullable()->change();
        });
    }
};
