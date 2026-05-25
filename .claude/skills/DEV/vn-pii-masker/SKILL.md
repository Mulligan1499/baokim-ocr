---
name: vn-pii-masker
description: Cross-domain skill mask PII Vietnamese trong logs/audit/export. Patterns hỗ trợ CCCD/CMND/passport/MST/phone VN/email/account number/IP/date/name VN. Giữ 4 ký tự cuối. KHÔNG mask trong response trả KSNB (cần raw để copy-paste).
when_to_use: |
  Triggers: "mask PII trong log", "che thông tin cá nhân", "ẩn CCCD trong audit",
  "anonymize log", "redact PII", "mask email trong export", "che số điện thoại
  trong error message", "PII masking cho compliance".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Cần strong anonymization cho ML training (skill này chỉ display masking, không cryptographic)
  - Mask binary data (cần specialized tool)
  - Mask trong response trả end user (KSNB cần raw để nhập liệu)
  - Mask file ảnh/PDF (skill này text-only — dùng image redaction tool riêng)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: cross
created: 2026-05-15
updated: 2026-05-25
tags: [cross, pii, masking, compliance, security, vietnam, pdpl, baokim]
---

# Vietnamese PII Masker

## Mục đích

Cross-domain skill che PII Việt Nam trong log, audit trail, error message, export. Mục đích:

1. **Compliance** với PDPL Việt Nam (hiệu lực 1/1/2026) — không lưu PII raw trong audit log
2. **Defense in depth** — nếu log bị leak, PII không đọc được trực tiếp
3. **Cross-domain reusable** — KSNB OCR, HR CV processing, Pháp chế contract review, Sales lead data, Ops monitoring

Distinction quan trọng: **mask cho LOG/AUDIT, không mask cho response trả user**. KSNB cần đọc CCCD đầy đủ để nhập liệu vào hệ thống, không thể mask. HR cần đọc số điện thoại ứng viên để liên hệ, không thể mask.

## Khi nào dùng skill này

✅ Mask PII trước khi ghi vào audit_logs table (BKM06, BKM08)
✅ Mask PII trong error message gửi monitoring (Sentry, Datadog)
✅ Mask PII trong CSV/Excel export cho phòng ban khác
✅ Mask PII trong meeting transcript trước khi share
✅ Mask PII trong API response logging (NGINX access log)
✅ Sanitize PII trong code example/documentation/README

❌ Anonymization cho ML training data (cần stronger: k-anonymity, differential privacy)
❌ Mask binary data (file ảnh, video, audio)
❌ Mask trong response trả end user (KSNB cần đọc raw)
❌ Mask PII trong production database (data tại rest cần encryption, không phải display masking)

## Workflow

### Step 1: Detect PII categories trong input

Skill scan input và detect 10 PII patterns:

| Category | Pattern detection | Example raw |
|---|---|---|
| CCCD 12 số | regex `\b\d{12}\b` | 001234567890 |
| CMND 9 số (legacy) | regex `\b\d{9}\b` (context-aware) | 012345678 |
| Passport VN | regex `\b[A-Z]\d{7}\b` | B1234567 |
| Passport foreign | regex `\b[A-Z]{1,2}\d{6,8}\b` | C12345678 |
| MST doanh nghiệp | regex `\b\d{10}(-\d{3})?\b` | 0123456789-001 |
| Phone VN | regex `\b0[35789]\d{8}\b` | 0987654321 |
| Email | regex `[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}` | john.doe@example.com |
| Full name VN | NLP/heuristic (3-4 từ, capitalize) | Nguyễn Văn An |
| Account number | regex `\b\d{10,16}\b` (context-aware) | 12345678901234 |
| IP address | regex IPv4/IPv6 | 192.168.1.100 |

**Context-aware**: 9 số có thể là CMND, mã giao dịch, hoặc random. Skill kiểm tra label/key xung quanh (`cmnd_number`, `tx_code`...) để decide.

### Step 2: Apply mask patterns

10 mask patterns chuẩn:

| Category | Raw | Masked | Rule |
|---|---|---|---|
| CCCD 12 số | 001234567890 | `001234***890` | Giữ 6 đầu + 3 cuối, mask 3 giữa |
| CMND 9 số | 012345678 | `012***678` | Giữ 3 đầu + 3 cuối |
| Passport | B1234567 | `B12***567` | Giữ chữ + 2 số đầu + 3 số cuối |
| MST 10 số | 0123456789 | `01234***89` | Giữ 5 đầu + 2 cuối |
| Phone VN | 0987654321 | `098****321` | Giữ 3 đầu + 3 cuối |
| Email | john.doe@example.com | `j***@example.com` | Giữ ký tự đầu + domain |
| Full name VN | Nguyễn Văn An | `Nguyễn V*** A` | Giữ họ + chữ cái đầu tên đệm + chữ cái đầu tên |
| Account number | 12345678901234 | `1234******1234` | Giữ 4 đầu + 4 cuối |
| IP v4 | 192.168.1.100 | `192.168.*.* ` | Giữ /16 prefix |
| Date of birth | 01/01/1990 | `**/**/1990` | Mask ngày + tháng, giữ năm |

**Rule chung**:
- Giữ đủ ký tự để debug (operations team biết đây là cùng entity nếu mask consistent)
- Mask đủ để không recover được PII chỉ từ masked version
- Consistent: cùng raw input → cùng masked output (dùng để correlation trong log)

### Step 3: Generate audit trail

Mỗi lần mask, return metadata:

```json
{
  "masked_text": "User Nguyễn V*** A (CCCD 001234***890) đã đăng ký",
  "pii_detected": [
    {"category": "full_name_vn", "count": 1, "first_match_position": 5},
    {"category": "cccd_12", "count": 1, "first_match_position": 28}
  ],
  "warnings": []
}
```

Audit trail dùng cho:
- Monitoring: alert khi PII count cao bất thường (potential data leak attempt)
- Debugging: dev biết log đã mask gì
- Compliance: prove rằng PII đã được handle

### Step 4: Code skeleton output

Khi user yêu cầu implementation (vd: Laravel middleware), skill output code PHP skeleton:

```php
namespace App\Services\Pii;

class VnPiiMasker
{
    public function mask(string $text): array
    {
        $piiDetected = [];
        $masked = $text;
        
        // CCCD 12 số
        $masked = preg_replace_callback(
            '/\b(\d{6})(\d{3})(\d{3})\b/',
            function ($m) use (&$piiDetected) {
                $piiDetected[] = ['category' => 'cccd_12', 'position' => 0];
                return $m[1] . '***' . $m[3];
            },
            $masked
        );
        
        // Phone VN
        $masked = preg_replace_callback(
            '/\b(0[35789]\d{2})(\d{3})(\d{3})\b/',
            function ($m) use (&$piiDetected) {
                $piiDetected[] = ['category' => 'phone_vn', 'position' => 0];
                return $m[1] . '****' . $m[3];
            },
            $masked
        );
        
        // ... (other patterns)
        
        return [
            'masked_text' => $masked,
            'pii_detected' => $piiDetected,
            'warnings' => [],
        ];
    }
}
```

## Examples

### Example 1: KSNB audit log (project OCR)

**Raw log line**:
```
[2026-05-15 10:30:00] User user_id=42 uploaded CCCD with id_number=001234567890, 
full_name=NGUYỄN VĂN AN, dob=01/01/1990. Request ID: 550e8400-e29b-41d4-a716-446655440000
```

**Masked output**:
```json
{
  "masked_text": "[2026-05-15 10:30:00] User user_id=42 uploaded CCCD with id_number=001234***890, full_name=NGUYỄN V*** A, dob=**/**/1990. Request ID: 550e8400-e29b-41d4-a716-446655440000",
  "pii_detected": [
    {"category": "cccd_12", "count": 1},
    {"category": "full_name_vn", "count": 1},
    {"category": "date_of_birth", "count": 1}
  ]
}
```

→ Lưu ý: `user_id=42`, `Request ID` không mask (đây là internal ID, không phải PII).

### Example 2: HR CV processing log (cross-domain)

**Raw log line**:
```
Candidate Trần Thị B (email: tran.b@gmail.com, phone: 0987654321) applied for 
Backend Engineer position. CCCD on file: 035195000001.
```

**Masked output**:
```json
{
  "masked_text": "Candidate Trần T*** B (email: t***@gmail.com, phone: 098****321) applied for Backend Engineer position. CCCD on file: 035195***001.",
  "pii_detected": [
    {"category": "full_name_vn", "count": 1},
    {"category": "email", "count": 1},
    {"category": "phone_vn", "count": 1},
    {"category": "cccd_12", "count": 1}
  ]
}
```

→ Demo reusability: cùng skill, đổi context. HR team có thể setup pipeline auto-mask logs trước khi gửi ra ngoài.

### Example 3: Sales lead export CSV (cross-domain, ops use)

**Raw CSV** trước khi gửi cho marketing partner:
```csv
name,email,phone,company,interest
Nguyễn Văn An,an.nguyen@company.vn,0901234567,FPT,Payment Gateway
Trần Thị B,tran.b@example.com,0987654321,VNG,POS Integration
```

**Masked output (CSV)**:
```csv
name,email,phone,company,interest
Nguyễn V*** A,a***@company.vn,090****567,FPT,Payment Gateway
Trần T*** B,t***@example.com,098****321,VNG,POS Integration
```

→ Use case: chia sẻ data với partner marketing, mask PII per PDPL compliance, nhưng vẫn giữ được info để partner contact lại nếu cần (giữ domain email, partial phone).

### Example 4: Sentry error message (cross-domain, ops)

**Raw error stack trace** chứa user input:
```
Exception: Failed to validate CCCD 012345678901 for user phone 0987654321
  at OcrService->extract() line 42
```

**Masked output**:
```json
{
  "masked_text": "Exception: Failed to validate CCCD 012345***901 for user phone 098****321\n  at OcrService->extract() line 42",
  "pii_detected": [
    {"category": "cccd_12", "count": 1},
    {"category": "phone_vn", "count": 1}
  ]
}
```

→ Sentry/Datadog logs share rộng trong team. Mask trước khi gửi là baseline compliance.

## What NOT to do

❌ KHÔNG mask trong response trả end user (KSNB cần đọc raw để nhập liệu — file 02 Section 7)
❌ KHÔNG mask ID nội bộ (user_id=42, request_id UUID) — đây không phải PII
❌ KHÔNG mask consistency-breaking (cùng raw → khác masked giữa các lần) — log correlation gãy
❌ KHÔNG mask quá strict (mask hết → debug bất khả thi) — pattern giữ đủ ký tự để dev biết "đây là cùng user"
❌ KHÔNG dùng skill này cho ML training data anonymization — cần stronger method (k-anonymity, DP)
❌ KHÔNG mask trong production DB column — cần encryption at rest, không phải display masking
❌ KHÔNG store masked data thay vì raw trong database (raw cần cho operations + có encryption at rest)

## Implementation notes

### Performance
- Regex-based: O(n) trên text length
- 10 patterns ≈ 10 regex passes — acceptable cho log lines, batch processing
- Cho high-throughput (>1000 req/s): cache compiled patterns, batch process

### Edge cases cần handle
- Số trong context không phải PII: tx_code 12 số, request_id UUID, timestamps
  → Solution: context-aware detection (kiểm tra key/label xung quanh)
- Tên VN có dấu vs không dấu: "Nguyễn Văn An" vs "Nguyen Van An" → cả 2 đều mask
- Email malformed: regex strict có thể miss → fallback heuristic
- Phone format có dấu cách: `098 765 4321` → normalize trước khi match
- Multi-line input: per-line scan, preserve newlines

### Integration với Laravel
- Middleware: `MaskPiiInLogMiddleware` mask response body trước khi ghi vào audit_logs
- Service: `VnPiiMasker` standalone service, inject vào logger
- Facade: `Mask::pii($text)` for convenience trong code

## Compliance reference

- **PDPL Việt Nam** (hiệu lực 1/1/2026): Personal Data Protection Law. Display masking là 1 control, không thay thế encryption at rest.
- **Nghị định 13/2023/NĐ-CP**: bảo vệ dữ liệu cá nhân, Article 11: data minimization principle.
- **Thông tư 39/2016/TT-NHNN**: retention 5 năm cho audit log financial.
- **GDPR Article 32** (nếu serve EU customers): pseudonymization là acceptable safeguard.

## Maintenance

- Update khi: thêm PII pattern mới (vd: bank account 16 số, MST cá nhân format mới)
- Update khi: PDPL hoặc nghị định ra hướng dẫn mới về masking
- Update khi: phát hiện false positive (mask cái không phải PII) hoặc false negative
- Version bump: minor cho thêm pattern, major cho breaking change format masked output
- Test prompts: xem `tests/prompts.md`

## Roadmap

- v0.2.0: tách `references/pii-patterns.md` với regex chi tiết + edge cases
- v0.3.0: thêm patterns specific industry: số bằng lái xe, số bảo hiểm xã hội, mã số thuế cá nhân
- v0.4.0: integration với Laravel logger automatic + middleware
- v1.0.0 (active): production-tested 1000+ log lines/day across 3+ services
