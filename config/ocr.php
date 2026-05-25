<?php

return [

    'storage_disk' => env('FILESYSTEM_DISK', 'local'),
    'storage_path_prefix' => 'ocr',

    'max_file_size_mb' => (int) env('OCR_MAX_FILE_SIZE_MB', 10),
    'max_pages' => (int) env('OCR_MAX_PAGES', 20),
    'idempotency_window_hours' => (int) env('OCR_IDEMPOTENCY_HOURS', 24),

    'classifier_unknown_threshold' => (float) env('OCR_CLASSIFIER_UNKNOWN_THRESHOLD', 0.5),

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
        'cccd', 'national_id_foreign', 'passport', 'gpkd',
        'contract_vi', 'contract_en', 'contract_zh',
        'invoice', 'legal_doc',
        'customs_declaration', 'bill_of_lading',
        'aml_charter', 'power_of_attorney',
        'labor_contract', 'financial_report',
        'other',
    ],

    'languages_supported' => ['vi', 'en', 'zh'],

    'llm_provider' => env('OCR_LLM_PROVIDER', 'gemini'),  // anthropic | gemini

    // Output token cap per stage. Đủ rộng cho tài liệu TQ dày (raw_text + translation_vi tốn token).
    // Gemini 2.5 Flash hỗ trợ output tới 65536 tokens.
    'max_tokens' => [
        'classifier' => (int) env('OCR_MAX_TOKENS_CLASSIFIER', 600),
        'extractor' => (int) env('OCR_MAX_TOKENS_EXTRACTOR', 16000),
        'judge' => (int) env('OCR_MAX_TOKENS_JUDGE', 4000),
    ],

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
        'high' => 0.85,           // ≥ 0.85 → quality=high
        'medium_min' => 0.50,     // < 0.5 → quality=low
        'flag_below' => 0.70,     // R4: < 0.7 → field flagged để KSNB review
    ],

    'queue' => env('OCR_QUEUE', 'default'),

];
