# Test prompts: vn-pii-masker

## Trigger tests (skill SHOULD activate)

1. "Mask PII trong log line này: [log]"
2. "Che CCCD trong audit log"
3. "Anonymize email trong CSV export"
4. "Redact phone number trong Sentry error message"
5. "Ẩn thông tin cá nhân ứng viên trong meeting transcript"
6. "PII masking cho audit_logs table Baokim"
7. "Mask số điện thoại trước khi gửi cho partner"
8. "Compliance check: log này có lộ PII không?"

**Pass criteria**: Skill load, detect các PII categories, output masked text + audit trail metadata.

## Anti-trigger tests (skill should NOT activate)

1. "Anonymize 10000 user records cho ML training" — cần stronger anonymization (k-anonymity, DP), không phải display masking
2. "Mask khuôn mặt trong ảnh CCCD" — image redaction, không phải text
3. "Ẩn CCCD trong response trả KSNB" — KSNB cần đọc raw, không mask trong response
4. "Encryption AES cho password column" — encryption, không phải masking
5. "Mask binary file content" — không scope skill này

## Output quality tests

### OQ-1: Multi-PII detection
**Input**: 
```
User Nguyễn Văn An (CCCD 001234567890, phone 0987654321, email an.nguyen@example.com)
```

**Expected output**:
- Masked: `User Nguyễn V*** A (CCCD 001234***890, phone 098****321, email a***@example.com)`
- Audit trail liệt kê 4 categories: full_name_vn, cccd_12, phone_vn, email
- Total PII count: 4

### OQ-2: False positive prevention
**Input**:
```
Transaction tx_code=550e84001234, user_id=42, request_id=550e8400-e29b-41d4-a716-446655440000
```

**Expected output**:
- Masked: nguyên vẹn (không mask gì)
- Audit trail: empty (PII count = 0)
- KHÔNG mask tx_code/user_id/UUID — đây là internal ID

### OQ-3: Context-aware ambiguity
**Input**:
```
CMND số 012345678 của khách hàng. Mã giao dịch 012345678 cho ref.
```

**Expected output**:
- Số đầu (label "CMND") → mask: `012***678`
- Số sau (label "Mã giao dịch") → KHÔNG mask
- Audit trail: 1 CMND detected, 0 false positive

### OQ-4: Consistency check
Run skill 3 lần với same input → output masked text giống nhau byte-by-byte

**Pass criteria**: Deterministic. Cùng raw → cùng masked (cho log correlation).

### OQ-5: Code skeleton request
**Input**: "Generate Laravel service code cho vn-pii-masker, áp dụng BKM02 (Eloquent only) + BKM03 (Service pattern)"

**Expected output**:
- PHP class skeleton `VnPiiMasker` trong namespace `App\Services\Pii`
- Methods: `mask(string $text): array`
- Return shape giống ví dụ trong SKILL.md
- KHÔNG dùng `DB::table` (BKM02 violation)

### OQ-6: Anti-trigger redirect — response masking
**Input**: "Mask CCCD trong response API trả KSNB"

**Expected output**:
- Skill REFUSE
- Explain: file 02 Section 7 — KSNB cần raw để nhập liệu
- Counter-propose: mask CHỈ trong audit log table, không mask response

## Regression tests

### RG-1: Pattern coverage
Run: input chứa cả 10 PII categories → check audit trail liệt kê đủ 10

**Pass**: 10/10 categories detected.

### RG-2: Deterministic output
Run same input 100 lần → check output deterministic

**Pass**: 100% same output.

### RG-3: Performance baseline
Run skill trên 1000 log lines × 100 chars/line

**Pass**: < 500ms total (regex compilation cached).

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
