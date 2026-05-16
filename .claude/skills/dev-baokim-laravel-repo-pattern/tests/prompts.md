# Test prompts: dev-baokim-laravel-repo-pattern

## Trigger tests (skill SHOULD activate)

1. "Code Laravel cho POST /api/v1/ocr/extract endpoint"
2. "Thiết kế PaymentService cho merchant tạo giao dịch"
3. "Repository pattern cho model User trong Baokim"
4. "Tách logic ra Service từ Controller này: [paste code]"
5. "Review code Controller có vi phạm BKM không"
6. "Viết Repository cho OcrDocument theo BKM03"
7. "Refactor Fat Controller thành 3 layer Baokim"
8. "Code Laravel feature theo pattern Repository/Service Baokim"

**Pass criteria**: Skill load, output 3 layer skeleton + BKM checklist.

## Anti-trigger tests (skill should NOT activate)

1. "Tối ưu query SELECT chậm trong PaymentRepository" — query optimization, dùng sql-reviewer
2. "Thiết kế schema bảng payment_logs" — pure schema, dùng db-schema-designer
3. "Code Symfony cho feature X" — non-Laravel framework
4. "Laravel best practice for cache" — generic Laravel docs
5. "Performance benchmark Eloquent vs Query Builder" — performance topic

## Output quality tests

### OQ-1: Full feature scaffolding
**Input**: "Code Laravel feature: tạo refund cho payment đã thanh toán. Cần validate signature, audit log, gửi callback merchant."

**Expected output**:
- 4 files generated: PaymentRefundController, PaymentRefundService, PaymentRefundRepository (hoặc reuse PaymentRepository), test
- Service có DB::transaction wrap
- Service có audit log call (BKM08)
- Pre-merge checklist 8 items BKM01-08
- KHÔNG có FOREIGN KEY (BKM01)
- KHÔNG có DB::table (BKM02)

### OQ-2: Code review mode
**Input** paste:
```php
class UserController {
    public function getUserOrders($userId) {
        $orders = DB::table('orders')->where('user_id', $userId)->get();
        foreach ($orders as $order) {
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->items = $items;
        }
        return $orders;
    }
}
```

**Expected output**:
- Violations report theo bảng (Rule ID | Severity | Issue | Fix):
  - BKM02 (M): DB::table → Eloquent
  - BKM03 (M): Controller chứa logic
  - BKM04 (M): N+1 query in foreach
- Refactor proposal: tách 3 layer + eager load `with('items')`

### OQ-3: Multi-rule violation detection
**Input** paste:
```php
class TransactionRepository {
    public function getMerchantTx($merchantId, $isActive) {
        if ($isActive) {
            return Transaction::where('merchant_id', $merchantId)->where('status', 1)->get();
        }
        return Transaction::where('merchant_id', $merchantId)->get();
    }
}
```

**Expected output**:
- BKM03 violation: Repository có if/else logic chọn query → tách thành 2 method
  - `findByMerchant(int $merchantId): Collection`
  - `findActiveByMerchant(int $merchantId): Collection`
- SCR04.2 (R): `where('status', 1)` magic number → constant

### OQ-4: BKM05 ID sizing decision
**Input**: "Code feature: log API requests (mỗi request 1 row, expected 10M rows/tháng). Cần Repository + Service."

**Expected output**:
- Suggest BIGINT cho id (BKM05: > 2 tỷ rows over lifetime)
- Suggest BKM06 partition (10M/tháng > threshold 5M)
- Migration generate có `PARTITION BY RANGE (TO_DAYS(created))`
- Repository methods: insert, findByRequestId, deleteOlderThan (cho retention)

### OQ-5: Cross-skill reference (OCR project)
**Input**: "Code Stage 4 PII Masker trong harness 7 stages của project OCR"

**Expected output**:
- Service `Stage4PiiMaskerService`
- Reference skill `vn-pii-masker` cho mask patterns (KHÔNG duplicate logic)
- Inject `VnPiiMasker` service vào constructor
- Return shape match Stage 5 input (consistency)

### OQ-6: Anti-trigger redirect
**Input**: "Query EXPLAIN cho find by merchant chạy chậm, optimize giúp"

**Expected output**:
- Skill REFUSE
- Redirect: "Đây là query performance, dùng `dev-baokim-sql-reviewer` skill (chưa build)"
- KHÔNG generate code refactor

## Regression tests

### RG-1: BKM coverage check
Run: "List ra tất cả BKM rules skill này check" → expect liệt kê BKM01-08 đầy đủ

**Pass**: 8/8 rules covered.

### RG-2: Layer separation enforcement
Run 5 different inputs (CRUD, batch, async, integration, report) → so sánh Controller skeletons

**Pass**: Tất cả Controller skeleton thin (< 20 lines/method), không có business logic.

### RG-3: No-FK consistency
Run 3 inputs có "user_id reference users table" → check migration output

**Pass**: 0/3 có `FOREIGN KEY` constraint. Validation referential trong Service.

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
