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

        // AC R6: PII masking giữ 4 ký tự cuối, vd 0123456789 → ******6789.
        // Apply uniformly cho CCCD/Passport/Phone/MST/Account number.

        // CCCD 12 digits → mask 8 + keep 4
        $text = preg_replace_callback(
            '/\b(\d{8})(\d{4})\b/',
            function ($m) use (&$detected) {
                $detected['cccd_12'] = ($detected['cccd_12'] ?? 0) + 1;
                return str_repeat('*', 8) . $m[2];
            },
            $text,
        );

        // Passport VN (1 uppercase letter + 7 digits) → mask all but last 4
        $text = preg_replace_callback(
            '/\b([A-Z])(\d{3})(\d{4})\b/',
            function ($m) use (&$detected) {
                $detected['passport'] = ($detected['passport'] ?? 0) + 1;
                return str_repeat('*', 4) . $m[3];
            },
            $text,
        );

        // Phone VN — 10 digits 03/05/07/08/09 → mask 6 + keep 4
        $text = preg_replace_callback(
            '/\b(0[35789]\d{2})(\d{2})(\d{4})\b/',
            function ($m) use (&$detected) {
                $detected['phone_vn'] = ($detected['phone_vn'] ?? 0) + 1;
                return str_repeat('*', 6) . $m[3];
            },
            $text,
        );

        // Email — keep last 4 chars before @ + full domain (giảm leak)
        $text = preg_replace_callback(
            '/\b([a-zA-Z0-9._-]+)(@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/',
            function ($m) use (&$detected) {
                $detected['email'] = ($detected['email'] ?? 0) + 1;
                $local = $m[1];
                $len = strlen($local);
                if ($len <= 4) {
                    return $local . $m[2];
                }
                return str_repeat('*', $len - 4) . substr($local, -4) . $m[2];
            },
            $text,
        );

        // MST 10 digits (optional -xxx suffix) → mask 6 + keep 4
        $text = preg_replace_callback(
            '/(?<!\d)(\d{6})(\d{4})(-\d{3})?(?!\d)/',
            function ($m) use (&$detected) {
                $detected['mst'] = ($detected['mst'] ?? 0) + 1;
                return str_repeat('*', 6) . $m[2] . ($m[3] ?? '');
            },
            $text,
        );

        // Account number 11-19 digits → mask all but last 4 (after CCCD/Phone/MST)
        $text = preg_replace_callback(
            '/(?<!\d)(\d{7,15})(\d{4})(?!\d)/',
            function ($m) use (&$detected) {
                $detected['account_number'] = ($detected['account_number'] ?? 0) + 1;
                return str_repeat('*', strlen($m[1])) . $m[2];
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
