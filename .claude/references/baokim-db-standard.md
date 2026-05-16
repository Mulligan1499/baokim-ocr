# Baokim Database Standards — Reference

> Foundation reference cho tất cả skills liên quan đến database design, query review, và migration tại Baokim. Đây KHÔNG phải skill — đây là knowledge base mà các skills sẽ reference đến.

**Nguồn**:
- Tài liệu chính: `Tiêu chuẩn cơ sở dữ liệu quan hệ — MySQL` (Baokim DSA Department)
- Coding rules bổ sung từ leadership Baokim (xem section "Baokim-Specific Additions")

**Cách dùng cho Claude**: Khi skill cần kiểm tra hoặc apply 1 rule, reference bằng rule ID (vd: SCR01.3, IDR02.2). Không nhúng toàn bộ nội dung vào skill SKILL.md — load on-demand từ file này.

---

## Convention quan trọng

**Severity prefix**:
- **(M)** Mandatory — bắt buộc tuân theo. Vi phạm → block PR.
- **(R)** Recommended — khuyến nghị. Vi phạm → warning, cần justify.
- **(D)** Deprecated — đã loại bỏ.

**Rule ID format**:
- `SCR<NN>.<sub>` — Schema rules
- `IDR<NN>.<sub>` — Index/Partition rules
- `SQR<NN>.<sub>` — Query rules
- `EXT<NN>.<sub>` — Extra/special rules
- `BKM<NN>` — Baokim-specific additions từ leadership

---

## Quick Reference Table

| Topic | Rule IDs | Key constraint |
|---|---|---|
| Table naming | SCR01.1-5 | snake_case, plural, ≤24 chars, FK: `<table_singular>_<col>` |
| Data types | SCR02.1-8 | DATETIME (không INT/STRING), decimal (không float), CHAR cho fixed-length |
| Column count | SCR06.1 | ≤30 cột/bảng |
| Normalization | SCR07.2 | 3NF |
| Index naming | IDR01.1-3 | `idx_`, `uk_`, `fk_` prefix |
| Index hygiene | IDR02.1-4 | Không redundant, size ≤30% data, ≤5 cột composite |
| Partition | IDR03.1-2 | >5tr records/tháng → partition; 2GB hoặc 5tr/partition |
| Query | SQR01-04 | Không `SELECT *`, LIMIT, IN ≤500 params, transaction ≤5000 rows |
| MySQL config | EXT04 | sql_mode bắt buộc: STRICT_TRANS_TABLES + 4 modes |
| **No FK** | **BKM01** | **Baokim: bỏ FK do performance** |
| Eloquent only | BKM02 | Cấm `DB::`, dùng Model |
| Architecture | BKM03 | Repo (query only) → Service (logic) → Controller (thin) |
| Loop queries | BKM04 | Cấm query trong for/foreach |
| ID sizing | BKM05 | Int nếu ≤2 tỷ rows; BIGINT khi cần |
| Log tables | BKM06 | Phải partition; bảng unique tách riêng |
| Hash search | BKM07 | Dùng DB hash index, không hash trong code |
| Fintech security | BKM08 | PII mask, audit log, encryption at rest |

---

## Section I: Schema Rules (SCR)

### SCR01: Quy tắc đặt tên

**SCR01.1 (M)** Sử dụng `snake_case` cho tên bảng, tên cột.
- ✅ `payment_transactions`, `user_id`
- ❌ `PaymentTransactions`, `userId`, `payment-transactions`

**SCR01.2 (M)** Tên bảng được đặt **số nhiều**.
- ✅ `users`, `shops`, `merchants`
- ❌ `user`, `shop`, `merchant`

**SCR01.3 (M)** Tên bảng/cột không quá **3 từ** và tối đa **24 ký tự**.
- ✅ `package_logs`, `channels_members`
- ❌ `merchant_payment_transaction_logs` (quá dài, dùng `mpt_logs` nếu cần)
- **Rationale**: framework sinh query dạng `table_name.column_name`, tên dài → query dài → khó đọc.

**SCR01.4 (M)** Không đặt trên bảng/cột trùng với MySQL keyword.
- ❌ `desc`, `range`, `order`, `key`, `group`, `status` (reserved in MySQL 8+)
- ✅ Dùng tên rõ nghĩa: `order_status`, `description`

**SCR01.5 (M)** FK column naming: `<table_singular>_<referenced_col>`. Nếu tên bảng dài → viết tắt.
- ✅ Bảng `bags(id, name)`, bảng `packages` reference → cột `bag_id`
- ✅ Bảng `package_wallet_fees(id, fee)`, bảng `packages` reference → cột `pwf_id` (viết tắt do dài)

### SCR02: Sử dụng data type

**SCR02.1 (M)** Table phải có PRIMARY KEY dạng số nguyên.
- BIGINT khi cần thiết (tránh tràn số)
- MySQL: auto_increment
- Distributed system: có thể dùng snowflake
- Trừ trường hợp bảng cần partition (xem IDR03)

**SCR02.2 (M)** Đảm bảo data type **nhất quán** giữa các bảng liên quan.
- ✅ `packages.pkg_order BIGINT` → `packages_money.pkg_order` cũng phải `BIGINT`
- **Rationale**: type mismatch → MySQL không dùng được index khi JOIN.

**SCR02.3 (R)** Chọn data type nhỏ nhất đủ dùng. Dùng `UNSIGNED` nếu không có giá trị âm.

| Type | Size | Range (UNSIGNED) |
|---|---|---|
| TINYINT | 1 byte | 0-255 |
| SMALLINT | 2 bytes | 0-65,535 |
| MEDIUMINT | 3 bytes | 0-16M |
| INT | 4 bytes | 0-4.2B |
| BIGINT | 8 bytes | 0-18.4 quintillion |

- IPv4 → `UNSIGNED INT` (4 bytes), KHÔNG dùng VARCHAR(15) (15+ bytes).

**SCR02.4 (M)** Time columns dùng `DATETIME`.
- ❌ KHÔNG dùng STRING, TIMESTAMP, INT để lưu thời gian.
- `created`, `modified`: `NOT NULL DEFAULT CURRENT_TIMESTAMP`

**SCR02.5 (M)** `DECIMAL` thay cho `FLOAT`/`DOUBLE`.
- **Rationale**: float có lỗi làm tròn → sai tiền. Fintech context: BẮT BUỘC dùng DECIMAL cho mọi thứ liên quan tiền.
- Ví dụ: `amount DECIMAL(18, 2)` cho VND.

**SCR02.6 (M)** `CHAR` cho dữ liệu **kích thước nhỏ và độ dài gần giống nhau** (lệch ≤2 ký tự).
- ✅ `phone CHAR(10)`, `id_number CHAR(12)`, `tx_code CHAR(16)`
- ❌ `name CHAR(50)` (độ dài tên biến thiên rất nhiều → VARCHAR)

**SCR02.7 (M)** Bảng có nhiều cột STRING **ít update hoặc kích thước lớn** → tách thành bảng riêng.
- Có ≥5 cột `VARCHAR(>255)` → tách
- Có ≥3 cột `TEXT` → tách

**SCR02.8 (M)** DEFAULT value phải đúng format/miền giá trị của cột.

### SCR03: Charset & Collation

**SCR03.1 (M)** Dùng `utf8mb4_unicode_ci` cho STRING columns.
- Lưu ý: bảng/cột mới có JOIN với cột tồn tại → kiểm tra collation match.

### SCR04: Comments

**SCR04.1 (M)** Comment ý nghĩa của bảng.

**SCR04.2 (M)** Comment đầy đủ các cột. Với `TINYINT` (số giá trị < 10), comment các giá trị có thể có.
- ✅ `status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=draft, 1=active, 2=disabled'`

**SCR04.3 (R)** Comment dùng tiếng Việt.

### SCR05: NOT NULL

**SCR05.1 (M)** Cột `NOT NULL` phải có `DEFAULT VALUE`.
- ✅ `quantity INT NOT NULL DEFAULT 0`
- ❌ `quantity INT NOT NULL` (insert thiếu → error)

### SCR06: Số lượng cột

**SCR06.1 (M)** Số cột mỗi bảng **≤ 30**.
- Nhóm cột theo nghiệp vụ/tần suất cập nhật → tách bảng nhỏ.
- **Rationale**: bảng nhiều cột → row size lớn → I/O nhiều → chậm.

### SCR07: Chuẩn hóa

**SCR07.2 (M)** Thiết kế theo **3NF**.
- Mỗi cột phụ thuộc trực tiếp vào PK, không phụ thuộc bắc cầu.
- Có thể de-normalize có chủ đích nếu performance yêu cầu, NHƯNG phải document trade-off.

---

## Section II: Index/Partition Rules (IDR)

### IDR01: Quy tắc đặt tên

**IDR01.1** Index: `idx_<col1>_<col2>_...`
- ✅ `idx_user_id_created`, `idx_status`

**IDR01.2** Unique index: `uk_<col1>_<col2>_...`
- ✅ `uk_email`, `uk_merchant_id_tx_code`

**IDR01.3** Foreign key (nếu dùng — xem BKM01): `fk_<source_table>_<col1>_<col2>_...`
- ✅ `fk_packages_bag_id`

### IDR02: Sử dụng index phù hợp

**IDR02.1 (M)** Không tạo index dư thừa.
- Index không được dùng bởi bất kỳ SELECT nào → drop (trừ PK, unique)
- **Index bao phủ**: nếu có `idx_c1_c2_c3(c1, c2, c3)` thì `idx_c1_c2(c1, c2)` là **dư thừa** (prefix bao phủ).

**IDR02.2 (M)** Tổng index size **≤ 30%** data size.
- Quá nhiều index → write chậm, disk lớn, buffer pool waste.

**IDR02.3 (M)** Composite index **không quá 5 cột**.

**IDR02.4 (R)** Index prefix cho cột STRING. Chú ý cardinality.
- ✅ `CREATE INDEX idx_name ON channels (NAME(12))` — prefix 12 ký tự
- Chọn độ dài prefix sao cho selectivity ≥ 90% so với full column.

### IDR03: Partitions

**IDR03.1 (M)** Cấu hình partition khi:
- Tăng trưởng dữ liệu > **5 triệu records/tháng**
- Mỗi partition: **2GB hoặc 5tr records**
- Các bảng dạng `logs`, `transactions`, v.v.

**IDR03.2 (R)** Mỗi partition tối thiểu **3 triệu records** (tránh quá nhiều partition nhỏ).

**Partition strategies thường dùng**:
- `RANGE` by date: log tables (vd: `PARTITION BY RANGE (TO_DAYS(created))`)
- `HASH` by user_id: user-related transactional data
- `KEY`: khi không có natural range/hash key

---

## Section III: SQL Rules (SQR)

### SQR01: Giới hạn kết quả

**SQR01.1 (M)** **Không** `SELECT *`. Chỉ định chính xác các cột cần.
- **Rationale**: `SELECT *` đọc cột không cần → tốn I/O, network. Khi schema thêm cột, code có thể vỡ.

**SQR01.2 (R)** Dùng `LIMIT`. Pagination dùng cơ chế **seek** (WHERE id > last_id), không dùng OFFSET lớn.
- ❌ `SELECT ... LIMIT 10000, 100` (OFFSET 10000 → scan 10100 rows)
- ✅ `SELECT ... WHERE id > 12345 ORDER BY id LIMIT 100` (seek)

### SQR02: Điều kiện truy vấn

**SQR02.1 (R)** Hạn chế `LIKE '%...'` hoặc `LIKE '%...%'`.
- Leading wildcard → không dùng được index B-tree.
- Nếu bắt buộc search text → cân nhắc full-text index hoặc external search (ES).

**SQR02.2 (M)** Số params trong query **≤ 500**.
- ❌ `WHERE user_id IN (1, 2, 3, ..., 550)` — 550 params
- Workaround: batch ≤500 hoặc dùng temp table / JOIN.

### SQR03: Explain

**SQR03.1 (R)** Dùng `SHOW WARNINGS` xem lỗi tiềm ẩn.

**SQR03.2 (R)** Sau `EXPLAIN`:
- `rows` ≤ **100k**
- `rows` ≤ **20%** tổng số rows của bảng
- Vi phạm → thêm index hoặc redesign query.

### SQR04: Transaction size

**SQR04.1 (M)** Kích thước transaction:
- Bảng ≤10 cột: ≤ **5000 rows affected/transaction**
- Bảng >10 cột: ≤ **1000 rows affected/transaction**
- **Rationale**: transaction lớn → lock dài → block other queries → undo log lớn.
- Workaround: chia batch nhỏ.

---

## Section IV: Extra Rules (EXT)

### EXT02: Đổi kiểu dữ liệu

**EXT02.1 (R)** Hạn chế đổi kiểu hoàn toàn (`char → int`, `int → datetime`).
- Migration phức tạp, data loss risk.

**EXT02.2 (M)** Mọi thay đổi kiểu dữ liệu → thông báo team DSA Department.

### EXT03: Phiên bản MySQL

**EXT03.1 (R)** Chỉ dùng MySQL LTS, còn support ≥ 1 năm tính từ EOL.

### EXT04: MySQL Configuration

**EXT04.1 (M)** `sql_mode` phải có **tối thiểu** các modes sau:
- `STRICT_TRANS_TABLES` — bật strict mode, reject invalid values
- `ONLY_FULL_GROUP_BY` — báo lỗi khi cột select/order không trong GROUP BY hoặc aggregate
- `NO_ZERO_IN_DATE` — reject ngày có 0 (`2025-00-15`)
- `NO_ZERO_DATE` — reject `0000-00-00`
- `ERROR_FOR_DIVISION_BY_ZERO` — báo lỗi chia 0

---

## Baokim-Specific Additions (BKM)

> Các rule bổ sung từ leadership Baokim, không có trong document gốc nhưng được áp dụng cho code Baokim.

### BKM01: KHÔNG dùng Foreign Key

**Rule (M)**: KHÔNG khai báo FK ở DB level (`FOREIGN KEY ... REFERENCES`).

**Rationale**:
- Performance hit khi insert/update/delete (FK check overhead)
- 2 bảng dính FK với traffic cao → cả 2 chậm theo
- Trade-off: mất referential integrity ở DB level → phải validate trong **Service layer** (xem BKM03)

**Hệ quả**:
- Cột reference vẫn dùng naming `<table_singular>_id` (vd: `bag_id`) — chỉ là quy ước, không có FK constraint
- Validation referential phải làm trong Service: trước khi insert `packages`, check `bags.id` exist
- ON DELETE CASCADE phải làm thủ công ở application layer

**Khi nào exception**: gần như không. Nếu thật sự cần FK (vd: master data ít thay đổi), document trade-off rõ.

### BKM02: Eloquent only, cấm Query Builder

**Rule (M)**: Cấm dùng `DB::table()`, `DB::raw()`, `DB::select()`. Bắt buộc dùng Eloquent Model.

**Rationale**:
- Eloquent type-safe (model attributes), Query Builder không
- Eager loading (`with()`) chỉ làm được qua Eloquent → chống N+1
- Code style nhất quán

**Exception duy nhất**: complex aggregation/reporting query không expressible qua Eloquent → vẫn phải dùng Eloquent's `DB::raw()` **bên trong** Model scope, không phải `DB::table()` standalone.

### BKM03: Repository / Service / Controller Pattern

**Rule (M)**: Code tách thành 3 layer rõ ràng.

```
Controller (thin)
    ↓ call
Service (business logic, orchestrate)
    ↓ call
Repository (1 function = 1 query, no logic)
    ↓ Eloquent
Model
```

**Repository Class**:
- Mỗi function chạy **đúng 1 query**
- KHÔNG có if/else lấy query này hay query kia
- KHÔNG validate, transform data
- Function name describe query intent: `findActiveByMerchantId`, `countCreatedToday`
- Repo của model nào CHỈ chứa func query model đó

```php
// ✅ Đúng
class PackageRepository {
    public function findActiveByMerchantId(int $merchantId): Collection {
        return Package::where('merchant_id', $merchantId)
                      ->where('status', 'active')
                      ->get();
    }
}

// ❌ Sai - có logic
class PackageRepository {
    public function findByMerchant(int $merchantId, bool $activeOnly) {
        if ($activeOnly) { ... } else { ... }   // Logic lấy query nào → thuộc Service
    }
}
```

**Service Class**:
- Logic nghiệp vụ, orchestrate nhiều Repo functions
- Validation referential (thay vì FK)
- Transaction management
- Call nhiều repository functions để xử lý

**Controller**:
- KHÔNG có logic
- Chỉ: validate request → call Service → return response
- Không gọi Repository trực tiếp (luôn qua Service)

### BKM04: Cấm query trong loop

**Rule (M)**: KHÔNG đặt query bên trong for/foreach/while.

```php
// ❌ Sai - N+1 problem
foreach ($users as $user) {
    $orders = OrderRepository::findByUserId($user->id);  // N queries
}

// ✅ Đúng - eager load
$users = User::with('orders')->get();  // 2 queries total

// ✅ Đúng - batch
$userIds = $users->pluck('id')->toArray();
$orders = OrderRepository::findByUserIds($userIds);  // 1 query
```

**Khi review code**: detect pattern `foreach .* Repository::|->Query|->where`.

### BKM05: ID Type Sizing

**Rule (R)**: Không phải bảng nào cũng cần `BIGINT`. Bảng nào tổng row dự kiến ≤ 2 tỷ → `INT UNSIGNED` đủ (4 bytes thay vì 8).

**Hướng dẫn quyết định**:
| Loại bảng | Suggested PK type |
|---|---|
| Bảng config, lookup, master nhỏ | `INT UNSIGNED` hoặc `SMALLINT UNSIGNED` |
| Bảng users, merchants (≤ vài chục triệu) | `INT UNSIGNED` |
| Bảng transactions, logs (hàng tỷ row dự kiến) | `BIGINT UNSIGNED` |
| Bảng có distributed insert (multi-region) | `BIGINT UNSIGNED` + snowflake |

**Trade-off**: BIGINT tốn gấp đôi INT cho mỗi index entry → tổng index size lớn → vi phạm IDR02.2 dễ hơn.

### BKM06: Log Tables

**Rule (M)**: Bảng log (audit_logs, request_logs, etc.) PHẢI partition theo time (xem IDR03).

**Pattern**:
```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- ... other cols
    PRIMARY KEY (id, created)  -- partition key must be in PK
) PARTITION BY RANGE (TO_DAYS(created)) (
    PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    -- ...
);
```

**Nếu cần unique constraint cross-partition** (vd: request_id unique toàn cục):
- KHÔNG dùng `UNIQUE KEY` (MySQL partition limitation: unique phải include partition key)
- Tách bảng phụ `audit_log_unique_keys(request_id PK, audit_log_id)` để enforce uniqueness.

### BKM07: Hash Search via DB Hash Index

**Rule (R)**: Khi cần search bằng hash (vd: lookup nhanh bằng SHA256), dùng **MEMORY engine với HASH index** hoặc index trên cột hash, không hash trong PHP code rồi search bằng `=`.

**Lý do**: DB hash index O(1) lookup; hash trong code cần WHERE = + B-tree index (O(log n)).

**Implementation**:
```sql
-- Cột lưu hash
hash_value CHAR(64) NOT NULL,  -- SHA256 hex
INDEX idx_hash_value USING HASH (hash_value)  -- MySQL InnoDB only supports B-tree;
                                                -- HASH literal applies to MEMORY engine
```

Với InnoDB (default), `USING HASH` bị ignore (vẫn dùng B-tree), nhưng **adaptive hash index** tự động build hot keys vào hash → vẫn nhanh hơn hash-in-code.

### BKM08: Fintech Security Priorities

**Rule (M)**: Là công ty fintech, mọi schema decision phải cân nhắc security.

**Checklist khi thiết kế bảng**:
- [ ] Cột chứa PII (CCCD, SĐT, email, address, account_number)? → Mark sensitive
- [ ] Sensitive cols cần encryption at rest? (vd: account_number → AES, không lưu plaintext)
- [ ] Bảng có audit trail không? Mọi UPDATE/DELETE sensitive data cần log vào `audit_logs`
- [ ] Soft delete (`deleted_at`) hay hard delete? Fintech mặc định **soft delete** cho compliance
- [ ] Có cột `created_by`, `updated_by` để trace ai động đến?
- [ ] PII đưa vào log có bị mask chưa?

---

## Decision Frameworks

### Khi nào dùng BIGINT vs INT cho PK?

```
Dự kiến row count cuối đời bảng?
├── < 100M rows  → INT UNSIGNED (đủ tới 4.2 tỷ)
├── 100M - 2B    → INT UNSIGNED (vẫn vừa)
├── > 2B         → BIGINT UNSIGNED
└── Distributed insert (multi-DC, snowflake) → BIGINT UNSIGNED bất kể size
```

### Khi nào partition bảng?

```
Tăng trưởng > 5tr records/tháng?  
├── CÓ  → Partition (RANGE by date thường dùng cho logs)
└── KHÔNG
    └── Tổng data dự kiến > 50GB?
        ├── CÓ  → Cân nhắc partition cho query performance
        └── KHÔNG → Không cần partition
```

### Khi nào thêm composite index?

```
Query có WHERE col_a = ? AND col_b = ?
├── col_a có cardinality cao đủ chọn riêng → index (col_a), không cần composite
├── col_a cardinality thấp, col_b cao → index (col_b, col_a) hoặc (col_b) only
├── Cả 2 cardinality vừa → composite (col_a, col_b)
└── Query có WHERE col_a = ? AND col_b > ? ORDER BY col_c
    → composite (col_a, col_b, col_c) — nguyên tắc equality first, range last
```

### VARCHAR sizing

```
Cố định độ dài? (phone, id_number, tx_code)
├── CÓ, lệch ≤2 ký tự → CHAR(n)
└── KHÔNG → VARCHAR(n) với n = max thực tế × 1.5 (buffer cho future)
              Nhỏ nhất có thể; tránh VARCHAR(255) cho mọi thứ vì lười.
```

---

## Common Anti-Patterns Caught

Khi review/design schema, watch out for:

1. **`status VARCHAR(20) NOT NULL DEFAULT 'active'`** → Should be `TINYINT UNSIGNED` với comment values (SCR02.3, SCR04.2)
2. **`amount FLOAT`** → DECIMAL (SCR02.5) — đặc biệt nguy hiểm với tiền
3. **`created INT NOT NULL`** lưu unix timestamp → DATETIME (SCR02.4)
4. **`id INT, FOREIGN KEY (user_id) REFERENCES users(id)`** → Bỏ FK (BKM01)
5. **Bảng `audit_logs` không partition** → Phải partition (BKM06, IDR03.1)
6. **Bảng 50 cột** → Tách bảng (SCR06.1)
7. **`name VARCHAR(255)` cho mọi cột string** → Size đúng nhu cầu
8. **Composite index 6+ cột** → Tách (IDR02.3)
9. **`utf8` thay vì `utf8mb4`** → Phải utf8mb4 (SCR03.1) — emoji + multi-byte chars
10. **Cột `password VARCHAR(64)` lưu plaintext** → Vi phạm BKM08; phải hash + salt

---

## How Skills Reference This Document

Skill SKILL.md không nên copy nội dung từ file này. Thay vào đó:

```markdown
## Validation steps

For schema validation, apply rules from `../../references/baokim-db-standard.md`:
1. Naming check: SCR01.1-5
2. Data type check: SCR02.1-8
3. Index review: IDR02.1-4
4. Partition decision: IDR03.1, BKM06
5. Architecture compliance: BKM01-03
```

Skill chỉ list **rule IDs** cần check; Claude khi run skill sẽ tự load reference này và áp dụng.

---

*Last updated: 2026-05-12 | Maintained by: Baokim Skills Team*