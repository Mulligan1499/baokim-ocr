<?php

namespace App\Services\Ocr;

use DateTimeImmutable;
use Throwable;

/**
 * Stage 3c — Cross-field logic validator (Verify Step level 2).
 *
 * Khác Stage 3a (regex per-field) và Stage 3b (LLM judge plausibility):
 * stage này kiểm tra **mối quan hệ giữa các field** bằng code thuần — math,
 * date logic, comparison. KHÔNG gọi LLM. Đây là "Deterministic Intervention"
 * theo pattern IBM Harness demo.
 *
 * Output: array warnings per rule fail + cờ `has_logic_error` để Stage 5 lower confidence.
 */
class Stage3CrossFieldValidator
{
    /**
     * @param  array<string,string>  $keyValuesRaw
     * @return array{warnings: array<int,array{code:string,msg:string,fields:array<int,string>}>, has_logic_error: bool}
     */
    public function validate(array $keyValuesRaw, string $docType): array
    {
        $warnings = match (true) {
            $docType === 'invoice' => $this->checkInvoice($keyValuesRaw),
            $docType === 'cccd' => $this->checkCccd($keyValuesRaw),
            $docType === 'passport' => $this->checkPassport($keyValuesRaw),
            str_starts_with($docType, 'contract_') => $this->checkContract($keyValuesRaw),
            $docType === 'gpkd' => $this->checkGpkd($keyValuesRaw),
            $docType === 'labor_contract' => $this->checkLaborContract($keyValuesRaw),
            default => [],
        };

        return [
            'warnings' => $warnings,
            'has_logic_error' => count($warnings) > 0,
        ];
    }

    // ── Invoice: subtotal + tax = total + invoice_date ≤ today ────────────────
    private function checkInvoice(array $kv): array
    {
        $warnings = [];

        $subtotal = $this->parseAmount($kv['subtotal'] ?? '');
        $tax = $this->parseAmount($kv['tax_amount'] ?? '');
        $total = $this->parseAmount($kv['total_amount'] ?? '');

        if ($subtotal !== null && $tax !== null && $total !== null) {
            $expected = $subtotal + $tax;
            $tolerance = max($total * 0.01, 1); // 1% hoặc 1 đơn vị
            if (abs($expected - $total) > $tolerance) {
                $warnings[] = [
                    'code' => 'MATH_MISMATCH',
                    'msg' => sprintf(
                        'Tổng tiền không khớp: subtotal %s + thuế %s = %s ≠ tổng %s.',
                        number_format($subtotal),
                        number_format($tax),
                        number_format($expected),
                        number_format($total),
                    ),
                    'fields' => ['subtotal', 'tax_amount', 'total_amount'],
                ];
            }
        }

        if ($d = $this->parseDate($kv['invoice_date'] ?? '')) {
            if ($d > new DateTimeImmutable('+1 day')) {
                $warnings[] = [
                    'code' => 'FUTURE_DATE',
                    'msg' => 'Ngày hóa đơn nằm trong tương lai.',
                    'fields' => ['invoice_date'],
                ];
            }
        }

        return $warnings;
    }

    // ── CCCD: tuổi cấp ≥ 14, hạn > ngày cấp ───────────────────────────────────
    private function checkCccd(array $kv): array
    {
        $warnings = [];

        $birth = $this->parseDate($kv['ngay_sinh'] ?? '');
        $issued = $this->parseDate($kv['ngay_cap'] ?? '');
        $expiry = $this->parseDate($kv['co_gia_tri_den'] ?? '');

        if ($birth && $issued) {
            $minIssue = $birth->modify('+14 years');
            if ($issued < $minIssue) {
                $warnings[] = [
                    'code' => 'AGE_AT_ISSUE_TOO_YOUNG',
                    'msg' => sprintf(
                        'Ngày cấp (%s) trước khi đủ 14 tuổi (sinh %s) — CCCD phải cấp sau 14 tuổi.',
                        $issued->format('d/m/Y'),
                        $birth->format('d/m/Y'),
                    ),
                    'fields' => ['ngay_sinh', 'ngay_cap'],
                ];
            }
        }

        if ($issued && $expiry && $expiry <= $issued) {
            $warnings[] = [
                'code' => 'EXPIRY_BEFORE_ISSUE',
                'msg' => 'Ngày hết hạn không sau ngày cấp.',
                'fields' => ['ngay_cap', 'co_gia_tri_den'],
            ];
        }

        return $warnings;
    }

    // ── Passport: expiry > date_of_issue ──────────────────────────────────────
    private function checkPassport(array $kv): array
    {
        $warnings = [];
        $issued = $this->parseDate($kv['date_of_issue'] ?? '');
        $expiry = $this->parseDate($kv['expiry_date'] ?? '');

        if ($issued && $expiry && $expiry <= $issued) {
            $warnings[] = [
                'code' => 'EXPIRY_BEFORE_ISSUE',
                'msg' => 'Passport: ngày hết hạn không sau ngày cấp.',
                'fields' => ['date_of_issue', 'expiry_date'],
            ];
        }

        if ($birth = $this->parseDate($kv['date_of_birth'] ?? '')) {
            if ($birth > new DateTimeImmutable('-14 years')) {
                $warnings[] = [
                    'code' => 'BIRTH_DATE_SUSPICIOUS',
                    'msg' => 'Ngày sinh < 14 tuổi — nghi vấn passport trẻ em (có thể cần kiểm tra thủ công).',
                    'fields' => ['date_of_birth'],
                ];
            }
        }

        return $warnings;
    }

    // ── Contract: signing ≤ today, expiry > signing, value > 0 ────────────────
    private function checkContract(array $kv): array
    {
        $warnings = [];

        $signKeys = ['ngay_ky', 'signing_date'];
        $expiryKeys = ['ngay_het_han', 'thoi_han_hop_dong', 'contract_end_date', 'effective_date'];
        $valueKeys = ['gia_tri', 'value', 'tong_gia_tri', 'total_value'];

        $sign = $this->firstDate($kv, $signKeys);
        $expiry = $this->firstDate($kv, $expiryKeys);
        $value = $this->firstAmount($kv, $valueKeys);

        if ($sign && $sign > new DateTimeImmutable('+1 day')) {
            $warnings[] = [
                'code' => 'FUTURE_DATE',
                'msg' => 'Ngày ký hợp đồng nằm trong tương lai.',
                'fields' => $this->firstFound($kv, $signKeys),
            ];
        }

        if ($sign && $expiry && $expiry <= $sign) {
            $warnings[] = [
                'code' => 'EXPIRY_BEFORE_SIGNING',
                'msg' => 'Ngày hết hạn hợp đồng không sau ngày ký.',
                'fields' => array_merge($this->firstFound($kv, $signKeys), $this->firstFound($kv, $expiryKeys)),
            ];
        }

        if ($value !== null && $value <= 0) {
            $warnings[] = [
                'code' => 'NON_POSITIVE_VALUE',
                'msg' => 'Giá trị hợp đồng phải > 0.',
                'fields' => $this->firstFound($kv, $valueKeys),
            ];
        }

        return $warnings;
    }

    // ── GPKD: ngay_thanh_lap ≤ ngay_cap, có MST ───────────────────────────────
    private function checkGpkd(array $kv): array
    {
        $warnings = [];

        $founded = $this->parseDate($kv['ngay_thanh_lap'] ?? '');
        $issued = $this->parseDate($kv['ngay_cap'] ?? '');

        if ($founded && $issued && $founded > $issued) {
            $warnings[] = [
                'code' => 'FOUNDED_AFTER_ISSUE',
                'msg' => 'Ngày thành lập sau ngày cấp giấy phép — bất hợp lý.',
                'fields' => ['ngay_thanh_lap', 'ngay_cap'],
            ];
        }

        $expiry = $this->parseDate($kv['ngay_het_han'] ?? '');
        if ($issued && $expiry && $expiry <= $issued) {
            $warnings[] = [
                'code' => 'EXPIRY_BEFORE_ISSUE',
                'msg' => 'GPKD: ngày hết hạn không sau ngày cấp.',
                'fields' => ['ngay_cap', 'ngay_het_han'],
            ];
        }

        return $warnings;
    }

    // ── Labor contract: start ≤ end, salary > 0 ───────────────────────────────
    private function checkLaborContract(array $kv): array
    {
        $warnings = [];

        $start = $this->parseDate($kv['contract_start_date'] ?? '');
        $end = $this->parseDate($kv['contract_end_date'] ?? '');

        if ($start && $end && $end <= $start) {
            $warnings[] = [
                'code' => 'END_BEFORE_START',
                'msg' => 'Ngày kết thúc HĐLĐ không sau ngày bắt đầu.',
                'fields' => ['contract_start_date', 'contract_end_date'],
            ];
        }

        $salary = $this->parseAmount($kv['salary'] ?? '');
        if ($salary !== null && $salary <= 0) {
            $warnings[] = [
                'code' => 'NON_POSITIVE_SALARY',
                'msg' => 'Lương phải > 0.',
                'fields' => ['salary'],
            ];
        }

        return $warnings;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseAmount(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // Bỏ separator (dấu chấm/phẩy/khoảng trắng), giữ dấu thập phân cuối nếu có
        $stripped = preg_replace('/[\s,]/', '', $raw);
        // Chỉ giữ digit + optional 1 dấu chấm
        $stripped = preg_replace('/[^\d.]/', '', $stripped);
        if ($stripped === '' || ! is_numeric($stripped)) {
            return null;
        }
        return (float) $stripped;
    }

    private function parseDate(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];
        foreach ($formats as $fmt) {
            try {
                $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $raw);
                if ($dt instanceof DateTimeImmutable && $dt->format($fmt) === $raw) {
                    return $dt;
                }
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    private function firstDate(array $kv, array $keys): ?DateTimeImmutable
    {
        foreach ($keys as $k) {
            if (! empty($kv[$k]) && $d = $this->parseDate($kv[$k])) {
                return $d;
            }
        }
        return null;
    }

    private function firstAmount(array $kv, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (! empty($kv[$k]) && ($a = $this->parseAmount($kv[$k])) !== null) {
                return $a;
            }
        }
        return null;
    }

    private function firstFound(array $kv, array $keys): array
    {
        foreach ($keys as $k) {
            if (! empty($kv[$k])) {
                return [$k];
            }
        }
        return [];
    }
}
