---
name: dev-baokim-sql-reviewer
description: Skill review SQL/DDL/Eloquent code theo standards Baokim. Apply SQR (Query), IDR (Index/Partition), BKM02/04/07. Output violations report với rule ID + severity (M/R) + fix proposal + EXPLAIN suggestion.
when_to_use: |
  Triggers: "review SQL query này", "check SQL có vi phạm SQR không",
  "review DDL CREATE TABLE", "review Repository code có query",
  "performance review query", "EXPLAIN analyze SQL", "kiểm tra index có dư thừa không",
  "tối ưu pagination N+1".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Schema design from scratch → dùng `dev-baokim-db-schema-designer`
  - Code architecture review không liên quan query → dùng `dev-baokim-laravel-repo-pattern`
  - NoSQL queries (MongoDB/Redis)
  - Pure database administration (backup, replication)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: dev
created: 2026-05-15
updated: 2026-05-25
tags: [dev, sql, review, performance, baokim, mysql, eloquent]
---

# Baokim SQL Reviewer

## Mục đích

Skill review SQL/DDL/Eloquent code theo Baokim DB Standards, giúp dev catch violations TRƯỚC khi merge PR. Encode tacit knowledge:
- SQR rules (Query): SELECT *, LIMIT, LIKE, IN, transaction size
- IDR rules (Index/Partition): redundant index, prefix coverage, partition decision
- BKM02/04/07: Eloquent only, no query in loop, hash search via DB index

**Reference foundation**: `05-baokim-db-standard.md` (sections SQR, IDR, BKM02/04/07)

## Khi nào dùng

✅ Code review PR có SQL/Eloquent code
✅ Optimize query chậm (EXPLAIN > 100k rows)
✅ Review migration DDL (CREATE TABLE, ALTER TABLE, CREATE INDEX)
✅ Investigate N+1 query trong code base
✅ Audit existing index, identify redundant ones

❌ Schema design from scratch — dùng `dev-baokim-db-schema-designer`
❌ Code 3-layer architecture review — dùng `dev-baokim-laravel-repo-pattern`
❌ NoSQL queries (MongoDB, Redis, Elasticsearch)
❌ Database administration topics (replication, backup, partitioning operations)

## Workflow

### Step 1: Classify input

| Input type | Approach |
|---|---|
| Raw SQL query | Check SQR rules + suggest EXPLAIN |
| Eloquent code | Check BKM02 + N+1 + SQR rules on resulting query |
| DDL CREATE TABLE | Check SCR rules (skill schema-designer overlap) + IDR partition |
| ALTER TABLE | Check migration impact + index hygiene |
| Repository class | Check 1-method-1-query (BKM03) + N+1 patterns |

### Step 2: Apply rules checklist

**SQR (Query rules)**:
- [ ] SQR01.1 (M): KHÔNG `SELECT *` — flag, suggest explicit columns
- [ ] SQR01.2 (R): Pagination dùng seek (`WHERE id > ?`), KHÔNG `LIMIT 10000, 100`
- [ ] SQR02.1 (R): Hạn chế `LIKE '%...'` (leading wildcard kills index)
- [ ] SQR02.2 (M): `IN (...)` ≤ 500 params
- [ ] SQR03.1 (R): Suggest `SHOW WARNINGS` cho cast issues
- [ ] SQR03.2 (R): EXPLAIN rows ≤ 100k AND ≤ 20% table
- [ ] SQR04.1 (M): Transaction rows: ≤5000 (bảng ≤10 cols) hoặc ≤1000 (bảng >10 cols)

**IDR (Index rules)**:
- [ ] IDR01.1-3: Index naming `idx_`, `uk_`, `fk_` prefix
- [ ] IDR02.1 (M): KHÔNG redundant index (prefix coverage)
- [ ] IDR02.2 (M): Total index size ≤ 30% data size
- [ ] IDR02.3 (M): Composite index ≤ 5 columns
- [ ] IDR02.4 (R): Index prefix cho VARCHAR, cardinality ≥ 90%
- [ ] IDR03.1 (M): Partition khi >5M rows/tháng

**BKM rules**:
- [ ] BKM02 (M): Eloquent only, cấm `DB::table()`, `DB::raw()` standalone
- [ ] BKM04 (M): KHÔNG query trong for/foreach/while
- [ ] BKM07 (R): Hash search dùng DB index, không hash trong PHP rồi `WHERE =`

### Step 3: Generate violations report

Format chuẩn:

```markdown
## SQL Review Report

### Critical violations (M) — block merge

| # | Rule ID | Line | Issue | Fix |
|---|---|---|---|---|
| 1 | BKM02 | L23 | `DB::table('users')` standalone | Dùng `User::query()` Eloquent |
| 2 | SQR01.1 | L42 | `SELECT *` từ `transactions` | Specify columns: `SELECT id, amount, created` |

### Recommended (R) — review with author

| # | Rule ID | Line | Issue | Fix |
|---|---|---|---|---|
| 3 | SQR01.2 | L67 | `LIMIT 50000, 100` slow OFFSET | Seek: `WHERE id > 50000 ORDER BY id LIMIT 100` |

### EXPLAIN suggestions

For query on L42, suggest running:
```sql
EXPLAIN SELECT id, amount, created FROM transactions WHERE ...;
```
Expected: rows ≤ 100k, type ≠ ALL, key NOT NULL.
```

### Step 4: Provide fix proposals

Mỗi violation có concrete fix (không chỉ flag), với code snippet showing before/after.

## Common Violations Detection

### Anti-pattern 1: SELECT *

**Bad**:
```php
$users = User::all();  // SELECT * FROM users
$users = DB::table('users')->get();  // Worse: SELECT * + DB::table
```

**Fix**:
```php
$users = User::query()->select(['id', 'email', 'created'])->get();
// Hoặc với scope: User::onlyEssentialColumns()->get();
```

### Anti-pattern 2: OFFSET pagination

**Bad**:
```php
$page = $request->page;
$result = Transaction::skip(($page-1) * 100)->take(100)->get();
// LIMIT (page-1)*100, 100 — slow for large offset
```

**Fix** (seek pagination):
```php
$lastId = $request->cursor ?? 0;
$result = Transaction::where('id', '>', $lastId)
                     ->orderBy('id')
                     ->limit(100)
                     ->get();
// Return next cursor in response
```

### Anti-pattern 3: N+1 query trong loop

**Bad**:
```php
foreach ($orders as $order) {
    $items = OrderItem::where('order_id', $order->id)->get();
    $order->items = $items;
}
// N+1: 1 query for orders + N queries for items
```

**Fix**:
```php
$orders = Order::with('items')->get();
// 2 queries total: orders + WHERE order_id IN (...)
```

### Anti-pattern 4: LIKE leading wildcard

**Bad**:
```php
User::where('email', 'LIKE', '%@gmail.com')->get();
// Full table scan, không dùng được index
```

**Fix**:
```php
// Suffix search: dùng REVERSE column + LIKE prefix
User::whereRaw('REVERSE(email) LIKE ?', ['moc.liamg@%'])->get();
// Hoặc dùng full-text search nếu cần
```

### Anti-pattern 5: IN large array

**Bad**:
```php
$ids = range(1, 1000);  // 1000 params
Transaction::whereIn('id', $ids)->get();  // SQR02.2 violation
```

**Fix**:
```php
foreach (array_chunk($ids, 500) as $chunk) {
    $batch = Transaction::whereIn('id', $chunk)->get();
    // process batch
}
```

### Anti-pattern 6: Hash in PHP then WHERE =

**Bad**:
```php
$hash = hash('sha256', $token);  // PHP hash
$session = UserSession::where('token_hash', $hash)->first();
// OK actually — đây không phải anti-pattern
```

**Vẫn OK** vì hash chỉ tính 1 lần, không loop. Anti-pattern là khi:

**Bad** (BKM07 violation):
```php
foreach ($tokens as $token) {
    $hash = hash('sha256', $token);  // hash trong loop!
    $session = UserSession::where('token_hash', $hash)->first();  // N queries
}
```

**Fix**:
```php
$hashes = array_map(fn($t) => hash('sha256', $t), $tokens);
$sessions = UserSession::whereIn('token_hash', $hashes)->get();  // 1 query
```

## Examples

### Example 1: Review Repository có N+1

**Input** (user paste):
```php
class OrderRepository {
    public function getOrdersWithItems(int $userId): Collection {
        $orders = DB::table('orders')->where('user_id', $userId)->get();
        foreach ($orders as $order) {
            $order->items = DB::table('order_items')->where('order_id', $order->id)->get();
        }
        return $orders;
    }
}
```

**Skill output**:

```markdown
## SQL Review Report

### Critical violations (M)

| # | Rule | Line | Issue | Fix |
|---|---|---|---|---|
| 1 | BKM02 | L3 | `DB::table('orders')` standalone | `Order::where(...)` |
| 2 | BKM02 | L5 | `DB::table('order_items')` standalone | `OrderItem::...` |
| 3 | BKM04 | L4-6 | Query trong foreach — N+1 | Eager load `with('items')` |
| 4 | BKM03 | L3-7 | Repository có logic (foreach loop) | Tách logic ra Service |

### Refactored:

```php
// Repository (1 method = 1 query)
class OrderRepository {
    public function findByUserIdWithItems(int $userId): Collection {
        return Order::with('items:id,order_id,name,quantity')
                    ->where('user_id', $userId)
                    ->get();
    }
}
// 2 queries: 1 for orders + 1 for items (Eloquent eager load)
```

### EXPLAIN suggestion:
```sql
EXPLAIN SELECT id, user_id, ... FROM orders WHERE user_id = 42;
-- Expected: type=ref, key=idx_user_id, rows < 100
```
```

### Example 2: Review migration thiếu partition

**Input**:
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('event_name');
    $table->json('payload');
    $table->dateTime('created')->useCurrent();
    $table->index('created');
});
```

**Skill output**:

```markdown
## DDL Review Report

### Critical violations (M)

| # | Rule | Issue | Fix |
|---|---|---|---|
| 1 | BKM06 | `audit_logs` không partition | Add partition RANGE by created |
| 2 | IDR03.1 | Log table > 5M rows/tháng → BẮT BUỘC partition | (same as #1) |

### Recommended fix:

```php
DB::statement("
    ALTER TABLE audit_logs 
    PARTITION BY RANGE (TO_DAYS(created)) (
        PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
        PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01'))
        -- ... add more partitions monthly
    )
");
```

### Also note:
- PRIMARY KEY must include `created` (partition key): `PRIMARY (id, created)`
- Adjust migration:
```php
$table->primary(['id', 'created']);
```
```

### Example 3: Review query có SELECT *

**Input**:
```sql
SELECT * FROM transactions WHERE merchant_id = ? AND status = 1 ORDER BY created DESC LIMIT 100;
```

**Skill output**:

```markdown
## Query Review

### Violations:
1. **SQR01.1 (M)**: `SELECT *` — specify needed columns
2. **SCR04.2 (R)**: `status = 1` magic number — use constant

### Refactored:
```sql
SELECT id, merchant_id, amount, status, tx_code, created
FROM transactions
WHERE merchant_id = ? AND status = 1  -- 1 = STATUS_SUCCESS
ORDER BY created DESC
LIMIT 100;
```

### EXPLAIN expected:
```
type: ref
possible_keys: idx_merchant_id_created
key: idx_merchant_id_created
rows: < 100
Extra: Using index condition
```

If rows > 1000 → consider composite index `(merchant_id, status, created)`.
```

### Example 4: Cross-skill — escalate to schema designer

**Input**: "Review this DDL: CREATE TABLE users (id INT, email VARCHAR(255), FOREIGN KEY ...)"

**Skill output**:

```markdown
This DDL needs more than query review — it's schema design.

For comprehensive schema review including:
- SCR rules (naming, data types, constraints)
- BKM01 (no FK)
- Initial design decisions

→ Use `dev-baokim-db-schema-designer` skill instead.

I can still review query-level concerns:
- BKM01: `FOREIGN KEY` violates rule — remove
- (other query concerns)
```

→ Skill aware of its scope, redirect appropriately.

### Example 5: Cross-skill — escalate to laravel-repo-pattern (Repository architecture issue)

**Input**: "Review Repository method:
```php
class OrderRepository {
    public function findOrdersWithItemsAndPayment(int $userId, bool $includeRefunded): Collection {
        $query = Order::where('user_id', $userId);
        if (!$includeRefunded) {
            $query->where('status', '!=', 'refunded');
        }
        $orders = $query->get();
        foreach ($orders as $order) {
            $order->items = OrderItem::where('order_id', $order->id)->get();
            $order->payment = Payment::where('order_id', $order->id)->first();
        }
        return $orders;
    }
}
```"

**Skill output**:

```markdown
This method has multiple architecture concerns beyond query review:

1. **BKM03 violation**: Repository có `if/else` switch query logic (line 5) — đó là business logic, thuộc Service, không phải Repository.

2. **BKM04 violation**: 2 queries trong foreach loop (line 9, 10) — N+1 pattern.

3. **BKM03 separation**: Repository nên có 2 methods nhỏ:
   - `findByUserId(int $userId, array $excludeStatuses = []): Collection` — pure query, no logic
   - Eager loading qua `with(['items', 'payment'])`

→ Để rewrite Repository theo BKM03 + design Service orchestration, dùng skill **`dev-baokim-laravel-repo-pattern`** — skill đó cover code architecture pattern.

I (sql-reviewer) đã catch N+1 + BKM04 violation ở query level. Sau khi skill `laravel-repo-pattern` refactor architecture, run lại `sql-reviewer` để verify EXPLAIN plan trên query mới.
```

→ Bi-directional chain: query review → architecture → query review verify.

## What NOT to do

❌ KHÔNG fix violation mà không explain rule ID + rationale
❌ KHÔNG suggest fix vi phạm BKM (vd: "thêm FK constraint cho referential integrity") — Baokim không dùng FK
❌ KHÔNG block PR vì recommended (R) violation — chỉ block critical (M)
❌ KHÔNG suggest premature optimization (chia partition khi bảng mới 10k rows)
❌ KHÔNG ignore N+1 — đây là performance killer phổ biến nhất
❌ KHÔNG approve query có `EXPLAIN rows > 100k` mà không investigate

## Reference foundation

- `05-baokim-db-standard.md`: full rule definitions (SQR, IDR, BKM)
- `dev-baokim-db-schema-designer`: schema design (overlap on DDL)
- `dev-baokim-laravel-repo-pattern`: code architecture (overlap on Repository)

## Maintenance & Roadmap

- v0.2.0: integration với phpstan/larastan custom rules cho auto-detect
- v0.3.0: thêm pattern detection: subquery anti-patterns, dangerous DELETE without LIMIT
- v1.0.0 (active): production-tested 50+ PRs

Tests: `tests/prompts.md`
