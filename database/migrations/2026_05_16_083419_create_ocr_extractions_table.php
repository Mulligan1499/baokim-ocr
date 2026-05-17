<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id')->comment('BKM01 no FK constraint');
            $table->string('language_detected', 10)->nullable()->comment('vi|en|zh|mixed|und');
            $table->string('doc_type', 50)->nullable()->comment('cccd|passport|gpkd|contract_*|invoice|...');
            $table->unsignedSmallInteger('page_count')->default(1);
            $table->longText('text_full')->nullable()->comment('RAW text — KSNB copy-paste (PII)');
            $table->longText('text_full_masked')->nullable()->comment('PII-masked for audit/export');
            $table->json('key_values')->nullable()->comment('RAW key-values (PII)');
            $table->json('key_values_masked')->nullable();
            $table->longText('translation_vi')->nullable();
            $table->decimal('confidence_overall', 4, 3)->nullable();
            $table->json('confidence_per_field')->nullable();
            $table->string('quality', 10)->nullable()->comment('high|medium|low');
            $table->boolean('requires_review')->default(false);
            $table->json('warnings')->nullable();
            $table->json('stage_metadata')->nullable()->comment('stage-by-stage outputs for debug');
            $table->timestamp('created_at')->nullable();

            $table->index('document_id');
            $table->index(['quality', 'created_at']);
            $table->index('doc_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_extractions');
    }
};
