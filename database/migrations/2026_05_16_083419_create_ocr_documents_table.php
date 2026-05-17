<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('hash', 64)->unique()->comment('sha256 for dedupe');
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('storage_path', 500);
            $table->string('status', 20)->default('pending')->comment('pending|processing|done|failed');
            $table->text('error_message')->nullable();
            $table->string('uploaded_via_api_key_label', 50)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_documents');
    }
};
