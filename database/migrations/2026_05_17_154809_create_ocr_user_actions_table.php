<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_user_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id')->comment('BKM01 no FK constraint');
            $table->string('field_key', 100);
            $table->string('action_type', 30)->comment('copy_raw|edit_then_copy|skip|mark_wrong|view_only');
            $table->text('original_value')->nullable()->comment('AI extracted (Stage 2 raw)');
            $table->text('final_value')->nullable()->comment('KSNB final after edit');
            $table->char('session_id', 36)->comment('UUID — group actions per upload session');
            $table->unsignedInteger('duration_ms')->nullable()->comment('Time from page load to action');
            $table->string('ksnb_user_label', 50)->nullable();
            $table->timestamp('analyzed_at')->nullable()->comment('Set by ocr:analyze-actions command');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'field_key'], 'idx_doc_field');
            $table->index(['action_type', 'created_at'], 'idx_action_time');
            $table->index('analyzed_at', 'idx_analyzed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_user_actions');
    }
};
