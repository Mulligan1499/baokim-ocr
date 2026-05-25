# Test prompts: dev-baokim-sql-reviewer

## Trigger tests (skill SHOULD activate)

1. "Review SQL query này: SELECT * FROM transactions WHERE merchant_id = 42"
2. "Check Repository code có vi phạm BKM02 không"
3. "Review DDL migration ALTER TABLE audit_logs"
4. "Tối ưu pagination N+1 trong code"
5. "EXPLAIN analyze giúp query này"
6. "Index dư thừa hay không trong bảng users"
7. "Check transaction size có vượt SQR04 không"
8. "Performance review query slow"

## Anti-trigger tests (skill should NOT activate)

1. "Thiết kế bảng users từ đầu" — schema design, dùng `dev-baokim-db-schema-designer`
2. "Tách Controller thành 3 layer" — code architecture, dùng `dev-baokim-laravel-repo-pattern`
3. "Query MongoDB tìm document" — NoSQL, không scope
4. "Setup MySQL replication" — DB admin, không phải code review
5. "Tăng size buffer pool MySQL" — DB tuning, không phải SQL review

## Output quality tests

### OQ-1: Multi-violation detection
**Input** paste:
```php
class TransactionRepository {
    public function getRecent($merchantId, $page) {
        return DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->offset($page * 100)
                ->limit(100)
                ->get();
    }
}
```

**Expected report**:
- BKM02 (M): DB::table → Eloquent Transaction::query()
- SQR01.1 (M): implicit SELECT * → specify columns
- SQR01.2 (R): OFFSET pagination cho large page chậm → seek pagination
- BKM03 (R): Repository có "$page * 100" tính toán → minor

### OQ-2: N+1 detection
**Input** paste:
```php
foreach ($orders as $order) {
    $items = OrderItem::where('order_id', $order->id)->get();
}
```

**Expected**:
- BKM04 (M): N+1 query in foreach
- Fix proposal: `Order::with('items')->get()` (eager load)
- Quantification: "N+1 = N queries; eager load = 2 queries"

### OQ-3: Missing partition detection
**Input** paste:
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->dateTime('created');
});
```

**Expected**:
- BKM06 (M): Log table không partition
- IDR03.1 (M): Volume > 5M/tháng → BẮT BUỘC partition
- Fix: add PARTITION BY RANGE(TO_DAYS(created))
- Note: PRIMARY KEY must include partition key

### OQ-4: Cross-skill escalation
**Input**: "Review DDL CREATE TABLE users với 30 columns, design tốt không?"

**Expected**:
- Skill RECOGNIZE: scope is schema design, not query review
- Redirect: "Use `dev-baokim-db-schema-designer` for comprehensive schema review"
- Vẫn check query-level concerns (FK, BKM01) nếu có

### OQ-5: False positive prevention
**Input**: 
```php
$hash = hash('sha256', $token);
UserSession::where('token_hash', $hash)->first();
```

**Expected**:
- KHÔNG flag BKM07 vì hash chỉ tính 1 lần (không trong loop)
- KHÔNG flag BKM02 vì dùng Eloquent
- Approve

### OQ-6: Severity calibration
**Input**: query có cả M và R violations

**Expected**:
- Separate report sections: "Critical (M) — block merge" vs "Recommended (R) — discuss"
- KHÔNG block PR vì R only
- KHÔNG approve PR có M violations

## Regression tests

### RG-1: Rule coverage
Run: "List ra tất cả rules skill này check" → expect ≥10 rules

**Pass**: Cover SQR01-04 + IDR01-03 + BKM02/04/07.

### RG-2: Fix proposal completeness
Run 5 different violations → check fix proposals

**Pass**: 5/5 có concrete code refactor (không chỉ flag, có code mẫu).

### RG-3: EXPLAIN suggestion
Run query review → check có EXPLAIN suggestion không

**Pass**: ≥80% review có EXPLAIN command kèm expected output.

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
