<?php

namespace App\Support;

/**
 * Lookup label tiếng Việt cho field key snake_case.
 *
 * Dictionary tại [config/ocr_field_labels.php](config/ocr_field_labels.php).
 * Nếu key không có trong dictionary → fallback humanized (str_replace _ → space, ucfirst).
 *
 * Mục đích: KSNB người Việt nhìn field "Số hộ chiếu" thay vì "passport_number".
 */
class OcrFieldLabels
{
    public static function label(string $key): string
    {
        $dict = config('ocr_field_labels', []);
        if (isset($dict[$key])) {
            return $dict[$key];
        }
        // fallback: humanize snake_case
        return ucfirst(str_replace('_', ' ', $key));
    }
}
