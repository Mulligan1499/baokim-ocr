<?php

return [

    'storage_disk' => env('FILESYSTEM_DISK', 'local'),
    'storage_path_prefix' => 'ocr',

    'max_file_size_mb' => (int) env('OCR_MAX_FILE_SIZE_MB', 25),
    'max_pages' => (int) env('OCR_MAX_PAGES', 20),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ],

    'rejected_mimes_hint' => [
        'application/msword' => 'Word .doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word .docx',
        'application/vnd.ms-excel' => 'Excel .xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel .xlsx',
    ],

    'api_key' => env('OCR_API_KEY'),
    'api_key_header' => 'X-API-Key',

    'doc_types' => [
        'cccd', 'passport', 'gpkd',
        'contract_vi', 'contract_en', 'contract_zh',
        'invoice', 'legal_doc',
        'customs_declaration', 'bill_of_lading',
        'aml_charter', 'power_of_attorney',
        'labor_contract', 'financial_report',
        'other',
    ],

    'languages_supported' => ['vi', 'en', 'zh'],

    'llm_provider' => env('OCR_LLM_PROVIDER', 'gemini'),  // anthropic | gemini

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model_classifier' => env('ANTHROPIC_MODEL_HAIKU', 'claude-haiku-4-5-20251001'),
        'model_extractor' => env('ANTHROPIC_MODEL_SONNET', 'claude-sonnet-4-6'),
        'model_judge' => env('ANTHROPIC_MODEL_HAIKU', 'claude-haiku-4-5-20251001'),
        'retry_attempts' => 3,
        'retry_delay_ms' => 1000,
        'timeout_seconds' => 60,
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model_classifier' => env('GEMINI_MODEL_CLASSIFIER', 'gemini-2.5-pro'),
        'model_extractor' => env('GEMINI_MODEL_EXTRACTOR', 'gemini-2.5-pro'),
        'model_judge' => env('GEMINI_MODEL_JUDGE', 'gemini-2.5-pro'),
        'retry_attempts' => 3,
        'retry_delay_ms' => 1000,
        'timeout_seconds' => 90,
    ],

    'cost_guard' => [
        'daily_limit_usd' => (float) env('OCR_DAILY_COST_LIMIT_USD', 10),
    ],

    'confidence_thresholds' => [
        'high' => 0.85,
        'medium_min' => 0.50,
    ],

    'queue' => env('OCR_QUEUE', 'default'),

];
