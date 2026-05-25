# Database Schema → Test Case Generation

Khi user upload **DDL SQL, ERD, schema JSON, migration file, hoặc mô tả schema**, áp dụng quy trình sau để generate test case database.

> Reference cơ sở: xem `platform-database.md`.

---

## 1. Phân loại input

| Loại input | Cách parse |
|---|---|
| **DDL SQL** (`CREATE TABLE...`) | Đọc cấu trúc — column, type, constraint, index |
| **ERD diagram** (PNG/PDF) | Phân tích image — table, relation |
| **Migration file** (Flyway/Liquibase/Alembic/Prisma) | Đọc thay đổi schema, cả forward và rollback |
| **Schema JSON** (Prisma schema, dbml, JSON Schema) | Parse |
| **Mô tả text** | Hỏi rõ field, type, constraint thiếu |

**Quy tắc**: schema thường thiếu thông tin về **business rule** (vd "soft delete với `deleted_at`", "audit log mọi update") → **hỏi user**.

---

## 2. Quy trình phân tích Schema → test case

### Bước 1 — Lập danh sách bảng & relation
Cho mỗi bảng, ghi nhận:
- Tên bảng
- Mục đích nghiệp vụ
- Mọi column: tên, type, nullable, default, constraint
- Primary key (single, composite, surrogate, natural)
- Foreign key + ON DELETE/UPDATE behavior
- Index (unique, composite, partial)
- Trigger
- View / Materialized view

### Bước 2 — Xác định loại test cần generate

| Use case | Loại test cần |
|---|---|
| Bảng mới được thêm | Schema validation, CRUD, constraint, index |
| Migration thay đổi schema | Migration test (forward, rollback, data integrity) |
| ETL/Data pipeline | Source-to-target, transform logic, idempotent |
| Performance optimization (index mới) | Performance test before/after |
| Refactor (rename, split table) | Backward compatibility, multi-phase migration |

### Bước 3 — Phân nhóm test case (sections)

Mỗi bảng/feature có ≥ các section sau:

1. **Schema validation** — column type, constraint, default đúng.
2. **CRUD** — INSERT, SELECT, UPDATE, DELETE từng kịch bản.
3. **Constraint enforcement** — NOT NULL, UNIQUE, FK, CHECK.
4. **Encoding & data type** — Unicode, emoji, decimal precision, datetime.
5. **Concurrency** — race condition, lock contention.
6. **Migration** (nếu là migration) — forward, backfill, rollback.
7. **Data integrity** — referential, logical, audit consistency.
8. **Performance** — query plan, slow query.
9. **Backup & restore** — nếu là bảng critical.

### Bước 4 — Áp dụng kỹ thuật
- Column có range/length → **BVA**.
- Column có enum → **EP**.
- Composite logic (vd "trạng thái A và type B thì status = X") → **Decision Table**.
- State của entity (Order: draft → submitted → paid → shipped) → **State Transition** trên DB.

### Bước 5 — Generate test case theo template

Mỗi test case có format:

| Cột | Nội dung |
|---|---|
| **Test Item** | "Verify INSERT user with email NULL — fail with NOT NULL constraint" |
| **Pre-Condition** | "Bảng users đã tồn tại với column email NOT NULL" |
| **Step** | SQL cụ thể: `INSERT INTO users (email, name) VALUES (NULL, 'test');` |
| **Expected Output** | "Lỗi: `null value in column "email" violates not-null constraint`<br>Không có record nào được thêm" |
| **Priority** | High / Medium / Low |

---

## 3. Test case bắt buộc cho mỗi loại constraint

### 3.1 NOT NULL
- INSERT với field NULL → fail
- INSERT với field empty string `""` (khác NULL) → success/fail tùy business
- UPDATE field NOT NULL thành NULL → fail
- ALTER COLUMN từ NULLABLE → NOT NULL khi đang có NULL → fail (cần backfill trước)

### 3.2 UNIQUE
- INSERT trùng giá trị → fail (duplicate key)
- INSERT NULL × 2 → tùy DB (PostgreSQL cho phép, MySQL không)
- Composite unique: chỉ trùng 1 trong 2 column → success
- UPDATE thành giá trị đã tồn tại → fail
- Unique case-insensitive: `Email@x.com` vs `email@x.com` — có duplicate?

### 3.3 PRIMARY KEY
- Auto-increment đúng, không skip lớn sau restart
- UUID không trùng (test tạo 100k record)
- Composite PK: chỉ trùng 1 phần → success
- DELETE không reuse PK (auto-increment)

### 3.4 FOREIGN KEY
- INSERT child với parent_id không tồn tại → fail
- DELETE parent có child:
  - `ON DELETE RESTRICT` → fail nếu có child
  - `ON DELETE CASCADE` → child xóa theo
  - `ON DELETE SET NULL` → child có FK = NULL
  - `ON DELETE NO ACTION` (deferred) → check tại commit
- UPDATE PK của parent — child có sync không (CASCADE)
- Soft delete parent — children FK reference đúng không?

### 3.5 CHECK constraint
- Vi phạm rule → fail (vd `age >= 0`)
- Boundary: vi phạm 1 → fail; valid biên → success
- NULL với CHECK: tùy expression, phải verify

### 3.6 DEFAULT
- INSERT không gửi field → giá trị default
- INSERT với NULL explicit → set NULL hay default?
- ALTER thêm column NOT NULL DEFAULT trên bảng có data → backfill thế nào?

### 3.7 INDEX
- Query có dùng index không (`EXPLAIN`)
- Composite index — column thứ 2 sortable nhưng không filterable một mình
- Partial index — chỉ áp dụng cho subset
- Unique index trùng → fail
- Drop index — query plan thay đổi đúng

---

## 4. Test case CRUD chi tiết

### CREATE (INSERT)
```sql
-- TC: INSERT đầy đủ field hợp lệ
INSERT INTO users (email, name, status) 
VALUES ('test@example.com', 'Test User', 'active');
-- Expected: 1 row inserted, id auto-generated, created_at = NOW(), updated_at = NOW()

-- TC: INSERT với field optional rỗng
INSERT INTO users (email, name) VALUES ('test@x.com', 'Test');
-- Expected: status = 'active' (default), 1 row inserted

-- TC: Bulk insert
INSERT INTO users (email, name) VALUES (...) -- 1000 records
-- Expected: 1000 rows, all id sequential, no skip

-- TC: INSERT vi phạm UNIQUE
-- Expected: fail with duplicate key error, no record added
```

### READ (SELECT)
```sql
-- TC: SELECT theo PK
SELECT * FROM users WHERE id = 1;
-- Expected: 1 row hoặc empty

-- TC: SELECT với JOIN có NULL ở bảng phải
SELECT u.*, o.id AS order_id FROM users u LEFT JOIN orders o ON u.id = o.user_id;
-- Expected: user không có order vẫn xuất hiện, order_id = NULL

-- TC: Aggregation với NULL
SELECT COUNT(*), COUNT(email_verified_at), AVG(age) FROM users;
-- Expected: COUNT(*) = total, COUNT(field) = non-null count, AVG bỏ qua NULL

-- TC: Pagination consistency
SELECT * FROM users ORDER BY id LIMIT 10 OFFSET 0;
SELECT * FROM users ORDER BY id LIMIT 10 OFFSET 10;
-- Expected: không trùng row, không thiếu row giữa 2 page (nếu không có insert giữa chừng)
```

### UPDATE
```sql
-- TC: UPDATE đúng record
UPDATE users SET name = 'New Name' WHERE id = 1;
-- Expected: 1 row affected, name thay đổi, updated_at được set, các field khác giữ nguyên

-- TC: UPDATE với WHERE không match
UPDATE users SET name = 'X' WHERE id = 99999;
-- Expected: 0 row affected, không lỗi

-- TC: UPDATE với optimistic lock (version field)
UPDATE users SET name = 'X', version = version + 1 WHERE id = 1 AND version = 5;
-- Expected: 1 row affected nếu version đang là 5; 0 nếu version đã thay đổi (concurrent update)

-- TC: UPDATE thiếu WHERE — bug nguy hiểm
UPDATE users SET status = 'banned';  -- KHÔNG có WHERE
-- Expected: cảnh báo trong code review, hoặc dùng safe_update mode
```

### DELETE
```sql
-- TC: DELETE đúng record
DELETE FROM users WHERE id = 1;
-- Expected: 1 row affected (hard) hoặc deleted_at set (soft)

-- TC: DELETE với cascade
DELETE FROM users WHERE id = 1;
-- Expected: orders, comments của user này cũng bị xóa (nếu CASCADE)

-- TC: DELETE với restrict — có child
DELETE FROM users WHERE id = 1;
-- Expected: fail nếu users.id còn được orders.user_id reference (ON DELETE RESTRICT)

-- TC: Soft delete — query mặc định không trả về
SELECT * FROM users WHERE deleted_at IS NULL;
-- Expected: không thấy user đã soft delete
```

---

## 5. Migration test (rất quan trọng)

Khi schema thay đổi qua migration, **bắt buộc** test:

### 5.1 Forward migration test
```
TC: Apply migration trên DB clone production
Steps:
1. Clone production DB → staging
2. Run migration script
3. Verify schema thay đổi đúng:
   - Column mới tồn tại với type đúng
   - Constraint mới được thêm
   - Index mới có
4. Verify data hiện có:
   - Row count không đổi
   - Sample 100 row — data không bị corrupt
   - Sum/avg các field numeric khớp before/after
5. Đo thời gian migration

Expected: schema đúng, data integrity preserved, time < N phút
```

### 5.2 Rollback migration test
```
TC: Rollback sau khi forward
Steps:
1. Forward migration done
2. Run rollback script
3. Verify schema = trước migration
4. Verify data = trước migration

Expected: idempotent — schema và data như cũ
```

### 5.3 Backfill data test
```
TC: Migration thêm column mới với default tính toán
Steps:
1. Migration thêm column total_orders với backfill = COUNT(orders)
2. Verify mỗi user có total_orders = số order thực

Expected: 100% user có giá trị đúng
```

### 5.4 Multi-phase migration (zero-downtime)
```
TC Phase 1: Add column (nullable)
TC Phase 2: Deploy code dual-write
TC Phase 3: Backfill data
TC Phase 4: Deploy code read-from-new
TC Phase 5: Drop column cũ

Mỗi phase: test rollback tới phase trước.
```

---

## 6. Test case data integrity (sau release)

Định kỳ chạy queries kiểm tra integrity:

```sql
-- Orphan: order trỏ vào user không tồn tại
SELECT o.id FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE u.id IS NULL;
-- Expected: 0 row

-- Logic: order.total = sum(line_items)
SELECT o.id, o.total, SUM(li.price * li.qty) AS calc
FROM orders o JOIN order_items li ON li.order_id = o.id
GROUP BY o.id, o.total
HAVING o.total <> SUM(li.price * li.qty);
-- Expected: 0 row

-- Duplicate
SELECT email, COUNT(*) FROM users GROUP BY email HAVING COUNT(*) > 1;
-- Expected: 0 row

-- Audit consistency
SELECT u.id FROM users u
WHERE u.updated_at > '2024-01-01'
AND NOT EXISTS (SELECT 1 FROM audit_log a WHERE a.entity_id = u.id);
-- Expected: 0 row (mọi update đều có audit)
```

→ Mỗi query là 1 test case, chạy định kỳ trong CI hoặc daily job.

---

## 7. Test case encoding & data type

### Unicode & Emoji
```sql
-- TC: Lưu tiếng Việt có dấu
INSERT INTO users (name) VALUES ('Nguyễn Văn Sơn');
SELECT name FROM users WHERE id = LAST_INSERT_ID();
-- Expected: trả về đúng "Nguyễn Văn Sơn", không phải "Nguy?n V?n S?n"

-- TC: Lưu emoji
INSERT INTO posts (content) VALUES ('Hello 🎉🌍');
-- Expected: lưu thành công nếu charset utf8mb4 (MySQL); fail nếu chỉ utf8 (3-byte)

-- TC: Sort tiếng Việt
SELECT name FROM users ORDER BY name COLLATE utf8mb4_vietnamese_ci;
-- Expected: thứ tự đúng a, ă, â, b, c...
```

### Datetime & Timezone
```sql
-- TC: Lưu UTC, đọc ra user timezone
INSERT INTO events (event_at) VALUES ('2024-11-15 10:00:00 UTC');
-- Client (GMT+7) đọc:
SELECT event_at AT TIME ZONE 'Asia/Ho_Chi_Minh' FROM events;
-- Expected: '2024-11-15 17:00:00'

-- TC: DST transition
-- Lưu event lúc 02:30 ngày DST đổi (giờ này không tồn tại) — handle thế nào?

-- TC: Year 2038 problem (UNIX timestamp 32-bit)
INSERT INTO logs (created_at) VALUES ('2038-01-19 03:14:08');
-- Expected: lưu được nếu type là DATETIME/TIMESTAMP 64-bit
```

### Decimal precision
```sql
-- TC: Field tiền tệ DECIMAL(10,2)
INSERT INTO orders (total) VALUES (99.999);
-- Expected: round/truncate thế nào? (DECIMAL thường round 100.00, FLOAT có sai số)

-- TC: Tính tổng nhiều record decimal
SELECT SUM(amount) FROM transactions;
-- Expected: chính xác, không lệch do float arithmetic
```

---

## 8. Test case concurrency

```sql
-- TC: Lost update
-- Session 1: BEGIN; SELECT balance FROM accounts WHERE id=1;  -- got 100
-- Session 2: BEGIN; SELECT balance FROM accounts WHERE id=1;  -- got 100
-- Session 1: UPDATE accounts SET balance = 100 - 50 WHERE id=1; COMMIT;
-- Session 2: UPDATE accounts SET balance = 100 - 30 WHERE id=1; COMMIT;
-- Expected: balance = 70 (sai), thực tế phải = 20.
-- → cần row lock (SELECT FOR UPDATE) hoặc optimistic lock

-- TC: Phantom read
-- Session 1: BEGIN; SELECT COUNT(*) FROM users WHERE status='active';  -- got 100
-- Session 2: INSERT INTO users (..., status='active'); COMMIT;
-- Session 1: SELECT COUNT(*) FROM users WHERE status='active';  -- ?
-- Expected tùy isolation level

-- TC: Deadlock
-- Session 1: UPDATE table1 ...; (acquire lock on row A)
-- Session 2: UPDATE table2 ...; (acquire lock on row B)
-- Session 1: UPDATE table2 (chờ row B);
-- Session 2: UPDATE table1 (chờ row A);
-- Expected: 1 session bị abort với deadlock error, retry handled by app
```

---

## 9. Ví dụ end-to-end

**Input** — user gửi DDL:
```sql
CREATE TABLE orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  total DECIMAL(12,2) NOT NULL CHECK (total >= 0),
  status VARCHAR(20) NOT NULL DEFAULT 'draft' 
    CHECK (status IN ('draft','submitted','paid','shipped','delivered','cancelled')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at TIMESTAMPTZ
);
CREATE INDEX idx_orders_user_id ON orders(user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_orders_status ON orders(status) WHERE deleted_at IS NULL;
```

**Sections sinh ra**:

| Section | TC count |
|---|---|
| Schema validation | 6 (column types, default, indexes exist) |
| INSERT — Constraints | 8 (NOT NULL each field, FK invalid, CHECK total negative, CHECK status invalid) |
| INSERT — Defaults | 3 (id auto, status='draft', created_at NOW) |
| SELECT — Soft delete filter | 3 (mặc định ẩn deleted_at, query có deleted_at thấy) |
| UPDATE — Status transition | 6 (draft→submitted, paid, cancel — valid; submitted→delivered — invalid) |
| UPDATE — updated_at | 1 (UPDATE → updated_at thay đổi) |
| DELETE — Soft delete | 2 (DELETE → deleted_at set; SELECT mặc định không thấy) |
| DELETE — FK RESTRICT | 1 (xóa user có order → fail) |
| Index usage | 3 (query by user_id dùng index, query by status dùng index) |
| Concurrency | 2 (2 user cùng update status) |
| Encoding | 2 (UUID format, decimal precision) |
| Audit | 1 (mọi UPDATE có audit log entry) |
| **Total** | **~38 TC** |

→ Output: file Excel `Orders_DB_TestCases.xlsx`, module_code = "DB_ORDERS".

---

## 10. Anti-pattern

| Anti-pattern | Sửa |
|---|---|
| Chỉ test happy path INSERT/SELECT | Phải test mọi constraint, mọi state |
| Test data hardcoded ID (id=1, id=2) | Dùng fixture/factory với dynamic ID |
| Không test migration trên data thực | Phải clone production (masked) |
| Không test rollback | Bắt buộc — không có rollback = không có release |
| Bỏ qua encoding test | Tiếng Việt/emoji thường lộ bug khi production |
| Không test FK behavior chi tiết | CASCADE vs RESTRICT vs SET NULL khác nhau hoàn toàn |
| Test chỉ trên DB rỗng | Bug volume-related không phát hiện |
| Không EXPLAIN query | Index không dùng → chậm khi scale |
