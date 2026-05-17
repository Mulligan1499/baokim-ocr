<?php

namespace App\Services\Ocr;

/**
 * Stage 3a — Rule-based programmatic validator (no LLM call).
 *
 * Per skill `ocr-extraction-validator`. Empty values pass — extractor saying "" is the
 * correct anti-hallucination behavior, not a rule failure.
 *
 * Returns per-key: rule_passed (bool), rule_reason (string).
 */
class Stage3RuleValidator
{
    /**
     * @param  array<string,string>  $keyValues
     * @return array<string,array{rule_passed:bool,rule_reason:string}>
     */
    public function validate(array $keyValues, string $docType): array
    {
        $out = [];
        foreach ($keyValues as $key => $value) {
            $value = is_string($value) ? trim($value) : (string) $value;
            $out[$key] = $this->check($key, $value);
        }
        return $out;
    }

    private function check(string $key, string $value): array
    {
        if ($value === '') {
            return ['rule_passed' => true, 'rule_reason' => 'empty value (extractor opted out)'];
        }

        $lower = strtolower($key);

        // CCCD — exactly 12 digits
        if ($lower === 'so_cccd' || $lower === 'cccd_number' || $lower === 'cccd') {
            $ok = (bool) preg_match('/^\d{12}$/', $value);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'CCCD 12 digits OK' : 'CCCD must be exactly 12 digits',
            ];
        }

        // Passport — letter(s) + 6-8 digits typical
        if ($lower === 'passport_number' || $lower === 'so_passport') {
            $ok = (bool) preg_match('/^[A-Z]{1,2}\d{6,8}$/i', $value);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'Passport format OK' : 'Passport: 1-2 letters + 6-8 digits',
            ];
        }

        // MST — 10 digits, optional -xxx suffix
        if ($lower === 'mst' || $lower === 'tax_code') {
            $ok = (bool) preg_match('/^\d{10}(-\d{3})?$/', $value);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'MST format OK' : 'MST must be 10 digits (optional -xxx)',
            ];
        }

        // Vietnamese phone
        if (str_contains($lower, 'phone') || str_contains($lower, 'sdt')) {
            $normalized = preg_replace('/\s+/', '', $value);
            $ok = (bool) preg_match('/^0[35789]\d{8}$/', $normalized);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'VN phone format OK' : 'VN phone: 10 digits starting 03/05/07/08/09',
            ];
        }

        // Email
        if (str_contains($lower, 'email')) {
            $ok = (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'Email OK' : 'Invalid email format',
            ];
        }

        // Date fields — try common formats
        if (str_contains($lower, 'date') || str_contains($lower, 'ngay') || str_contains($lower, 'dob')) {
            return $this->checkDate($value);
        }

        // Amount / monetary
        if (str_contains($lower, 'amount') || str_contains($lower, 'value') || str_contains($lower, 'gia_tri') || str_contains($lower, 'salary')) {
            $stripped = preg_replace('/[\s.,]/', '', $value);
            $ok = (bool) preg_match('/^\d+(\.\d+)?$/', $stripped);
            return [
                'rule_passed' => $ok,
                'rule_reason' => $ok ? 'Numeric amount OK' : 'Amount must be numeric (optional decimals)',
            ];
        }

        // Unknown field type — pass through (we don't enforce)
        return ['rule_passed' => true, 'rule_reason' => 'no rule (pass-through)'];
    }

    private function checkDate(string $value): array
    {
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $fmt, $value);
            if ($dt instanceof \DateTimeImmutable && $dt->format($fmt) === $value) {
                $year = (int) $dt->format('Y');
                if ($year < 1900 || $dt > new \DateTimeImmutable('+1 day')) {
                    return ['rule_passed' => false, 'rule_reason' => "Date out of range (year={$year})"];
                }
                return ['rule_passed' => true, 'rule_reason' => "Date {$fmt} OK"];
            }
        }
        return ['rule_passed' => false, 'rule_reason' => 'Unrecognized date format'];
    }
}
