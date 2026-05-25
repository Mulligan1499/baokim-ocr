<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocr_documents', function (Blueprint $table) {
            $table->char('request_id', 36)->nullable()->after('id');
            $table->timestamp('processed_at')->nullable()->after('error_message');
            $table->timestamp('cached_until')->nullable()->after('processed_at')
                ->comment('AC-E05 idempotency cache window (24h after first success)');
        });

        foreach (DB::table('ocr_documents')->whereNull('request_id')->pluck('id') as $id) {
            DB::table('ocr_documents')->where('id', $id)->update(['request_id' => (string) Str::uuid()]);
        }

        Schema::table('ocr_documents', function (Blueprint $table) {
            $table->char('request_id', 36)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ocr_documents', function (Blueprint $table) {
            $table->dropUnique(['request_id']);
            $table->dropColumn(['request_id', 'processed_at', 'cached_until']);
        });
    }
};
