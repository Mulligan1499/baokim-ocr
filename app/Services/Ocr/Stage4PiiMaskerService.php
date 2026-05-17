<?php

namespace App\Services\Ocr;

/**
 * Stage 4 — Vietnamese PII masker (from skill vn-pii-masker).
 *
 * Masks: CCCD (12), CMND (9), Passport VN, MST, Phone VN, Email, Account number,
 * IP v4, Date of birth, Full name VN (best-effort).
 *
 * Used for `text_full_masked` + `key_values_masked` + audit log payloads.
 * The RAW versions are kept for KSNB copy-paste workflow.
 */
class Stage4PiiMaskerService
{
    /**
     * Mask PII in arbitrary text.
     *
     * @return array { masked: string, detected: array<string, int> }
     */
    public function maskText(string $text): array
    {
        $detected = [];

        // CCCD 12 digits — keep 6 + 3, mask 3 in middle
        $text = preg_replace_callback(
            '/\b(\d{6})(\d{3})(\d{3})\b/',
            function ($m) use (&$detected) {
                $detected['cccd_12'] = ($detected['cccd_12'] ?? 0) + 1;
                return $m[1] . '***' . $m[3];
            },
            $text,
        );

        // Passport VN (1 uppercase letter + 7 digits)
        $text = preg_replace_callback(
            '/\b([A-Z])(\d{2})\d{2}(\d{3})\b/',
            function ($m) use (&$detected) {
                $detected['passport'] = ($detected['passport'] ?? 0) + 1;
                return $m[1] . $m[2] . '**' . $m[3];
            },
            $text,
        );

        // Phone VN — 10 digits 03/05/07/08/09 → keep 3 + mask 4 + keep 3
        $text = preg_replace_callback(
            '/\b(0[35789]\d)(\d{4})(\d{3})\b/',
            function ($m) use (&$detected) {
                $detected['phone_vn'] = ($detected['phone_vn'] ?? 0) + 1;
                return $m[1] . '****' . $m[3];
            },
            $text,
        );

        // Email — keep first char + domain
        $text = preg_replace_callback(
            '/\b([a-zA-Z0-9._-])([a-zA-Z0-9._-]+)(@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/',
            function ($m) use (&$detected) {
                $detected['email'] = ($detected['email'] ?? 0) + 1;
                return $m[1] . '***' . $m[3];
            },
            $text,
        );

        // MST 10 digits (optional -xxx suffix). Run after CCCD to avoid double-match.
        // Need negative lookbehind for 12 digits (CCCD already masked to ***)
        $text = preg_replace_callback(
            '/(?<!\d)(\d{4})(\d{4})(\d{2})(-\d{3})?(?!\d)/',
            function ($m) use (&$detected) {
                $detected['mst'] = ($detected['mst'] ?? 0) + 1;
                return $m[1] . '****' . $m[3] . ($m[4] ?? '');
            },
            $text,
        );

        // Account number 12-16 digits — best-effort, after CCCD/MST masked
        $text = preg_replace_callback(
            '/(?<!\d)(\d{4})(\d{4,8})(\d{4})(?!\d)/',
            function ($m) use (&$detected) {
                $detected['account_number'] = ($detected['account_number'] ?? 0) + 1;
                return $m[1] . str_repeat('*', strlen($m[2])) . $m[3];
            },
            $text,
        );

        // Date of birth DD/MM/YYYY → **/**/YYYY
        $text = preg_replace_callback(
            '/\b\d{1,2}\/\d{1,2}\/(\d{4})\b/',
            function ($m) use (&$detected) {
                $detected['date'] = ($detected['date'] ?? 0) + 1;
                return '**/**/' . $m[1];
            },
            $text,
        );

        // IP v4 — keep /16 prefix
        $text = preg_replace_callback(
            '/\b(\d{1,3}\.\d{1,3})\.\d{1,3}\.\d{1,3}\b/',
            function ($m) use (&$detected) {
                $detected['ip_v4'] = ($detected['ip_v4'] ?? 0) + 1;
                return $m[1] . '.*.*';
            },
            $text,
        );

        // Vietnamese full name (3-4 capitalized words) — best-effort heuristic
        $text = preg_replace_callback(
            '/\b([A-ZĐÀẢÃÁẠÂẦẨẪẤẬĂẰẲẴẮẶÈẺẼÉẸÊỀỂỄẾỆÌỈĨÍỊÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢÙỦŨÚỤƯỪỬỮỨỰỲỶỸÝỴ][a-zàảãáạâầẩẫấậăằẳẵắặèẻẽéẹêềểễếệìỉĩíịòỏõóọôồổỗốộơờởỡớợùủũúụưừửữứựỳỷỹýỵđ]+)\s+([A-ZĐÀẢÃÁẠÂẦẨẪẤẬĂẰẲẴẮẶÈẺẼÉẸÊỀỂỄẾỆÌỈĨÍỊÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢÙỦŨÚỤƯỪỬỮỨỰỲỶỸÝỴ])[a-zàảãáạâầẩẫấậăằẳẵắặèẻẽéẹêềểễếệìỉĩíịòỏõóọôồổỗốộơờởỡớợùủũúụưừửữứựỳỷỹýỵđ]+\s+([A-ZĐÀẢÃÁẠÂẦẨẪẤẬĂẰẲẴẮẶÈẺẼÉẸÊỀỂỄẾỆÌỈĨÍỊÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢÙỦŨÚỤƯỪỬỮỨỰỲỶỸÝỴ])[a-zàảãáạâầẩẫấậăằẳẵắặèẻẽéẹêềểễếệìỉĩíịòỏõóọôồổỗốộơờởỡớợùủũúụưừửữứựỳỷỹýỵđ]+\b/u',
            function ($m) use (&$detected) {
                $detected['full_name_vn'] = ($detected['full_name_vn'] ?? 0) + 1;
                return $m[1] . ' ' . $m[2] . '*** ' . $m[3];
            },
            $text,
        );

        return [
            'masked' => $text,
            'detected' => $detected,
        ];
    }

    /**
     * Mask PII inside a key->value map (Stage 2 key_values).
     * Returns { masked_kv, detected }.
     */
    public function maskKeyValues(array $keyValues): array
    {
        $maskedKv = [];
        $detected = [];
        foreach ($keyValues as $key => $value) {
            if (! is_string($value)) {
                $maskedKv[$key] = $value;
                continue;
            }
            $result = $this->maskText($value);
            $maskedKv[$key] = $result['masked'];
            foreach ($result['detected'] as $cat => $cnt) {
                $detected[$cat] = ($detected[$cat] ?? 0) + $cnt;
            }
        }
        return ['masked_kv' => $maskedKv, 'detected' => $detected];
    }
}
