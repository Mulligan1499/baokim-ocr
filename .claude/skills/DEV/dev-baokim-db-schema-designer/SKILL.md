---
name: dev-baokim-db-schema-designer
description: Skill design DB schema FROM SCRATCH theo Baokim standards. Apply SCR/IDR/BKM rules tại gen-time (không phải review-time). Encode 8 trap Laravel default vs Baokim convention. Output Laravel migration + raw DDL + index/partition decision + pre-merge checklist.
when_to_use: |
  Triggers: "tạo migration cho [feature]", "thiết kế bảng [entity]", "viết DDL
  CREATE TABLE cho", "design schema cho [feature]", "Laravel migration cho [model]",
  "tạo bảng mới [name]", "schema design [entity]", "DB design cho [feature]",
  "thiết kế cơ sở dữ liệu cho", "viết migration mới cho".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Review existing schema → dùng `dev-baokim-sql-reviewer`
  - Code Repository/Service không liên quan schema → dùng `dev-baokim-laravel-repo-pattern`
  - Pure SQL query review → dùng `dev-baokim-sql-reviewer`
  - NoSQL/MongoDB/Redis (skill này MySQL-specific)
  - Schema migration data-only (vd UPDATE values)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: dev
created: 2026-05-24
updated: 2026-05-25
tags: [dev, schema, migration, baokim, mysql, laravel, generate]
---

# Baokim DB Schema Designer

## Mục đích

Skill design schema mới theo Baokim standards **TẠI THỜI ĐIỂM SINH CODE**, không phải khi review. Đây là gap mà `dev-baokim-sql-reviewer` không cover (skill đó chỉ trigger khi user nói "review" — không trigger khi sinh code mới).

**Vấn đề skill này giải quyết** (đã verify từ chính dự án OCR Baokim — gây ra 8 violation Critical trong 4 bảng):

- AI khi sinh migration Laravel có xu hướng dùng **default ergonomics** (`$table->timestamps()`, `$table->id()`) — Laravel default conflict với SCR02.4 (TIMESTAMP vs DATETIME), IDR01 (index naming), BKM01 (FK constraint khi dùng `$table->foreignId()`).
- Không có **active reminder** khi sinh code → AI fall back về training data global (Stack Overflow, Laravel docs).
- Skill này encode rule **TRƯỚC** lúc sinh, không sau.

**Reference foundation**: `.claude/references/baokim-db-standard.md` (full SCR/IDR/BKM rules).

## Khi nào dùng

✅ Tạo migration cho feature mới
✅ Design schema cho entity mới chưa có
✅ Viết DDL CREATE TABLE raw (không qua Laravel)
✅ Thêm bảng mới vào project Baokim existing
✅ Refactor schema split (vd: SCR02.7 tách bảng TEXT)
✅ Thiết kế index strategy cho query pattern mới

❌ Review SQL query/DDL đã có → `dev-baokim-sql-reviewer`
❌ Code Repository/Service không touch schema → `dev-baokim-laravel-repo-pattern`
❌ Migration data-only (UPDATE/INSERT data, không ALTER schema)
❌ NoSQL (MongoDB, Redis, ES) — skill này MySQL-specific
❌ Pure performance tuning query → `dev-baokim-sql-reviewer`

## Workflow

### Step 1: Hiểu nghiệp vụ + identify entities

Hỏi user (hoặc infer từ context):
- Entity là gì? (vd: order, transaction, user, audit_log)
- Volume dự kiến: <100k / <10M / >100M rows? (quyết BKM05 ID sizing)
- Tăng trưởng: <1M/tháng / >5M/tháng? (quyết IDR03 partition)
- Quan hệ với entity khác? (BKM01 → soft ref qua naming, không FK)
- Có chứa PII? (BKM08 → cân nhắc mask + audit)
- Có cột TEXT/JSON kích thước lớn? (SCR02.7 cân nhắc tách bảng)
- Query pattern thường dùng? (quyết IDR02 index design)

### Step 2: Apply 8 nhóm rule khi design

**Khi viết mỗi cột, check thứ tự**:

**A. NAMING (SCR01)**:
- [ ] Table snake_case, plural, ≤24 chars, ≤3 words
- [ ] Column snake_case, không trùng MySQL reserved word (`event`, `desc`, `range`, `order`, `key`, `group`, `status` standalone)
- [ ] FK col naming `<table_singular>_<col>` (nếu tên dài → viết tắt)

**B. DATA TYPE (SCR02 + BKM05 + BKM08)**:
- [ ] PK: số nguyên auto_increment. Tổng row dự kiến:
   - <100M → `INT UNSIGNED` (BKM05)
   - >2B hoặc multi-DC → `BIGINT UNSIGNED`
- [ ] Time columns: **DATETIME** (KHÔNG TIMESTAMP) — `NOT NULL DEFAULT CURRENT_TIMESTAMP` (SCR02.4)
- [ ] Tên cột thời gian: `created`, `modified` (chuẩn Baokim) hoặc `created_at`, `updated_at` (giữ Laravel ergonomics — document trade-off)
- [ ] Tiền/decimal: **DECIMAL(18,2) hoặc DECIMAL(18,4)** (KHÔNG FLOAT/DOUBLE) — SCR02.5 fintech mandatory
- [ ] Fixed-length data: `CHAR(n)` (vd: `hash CHAR(64)`, `phone CHAR(10)`, `tx_code CHAR(16)`)
- [ ] Variable-length string: `VARCHAR(n)` với n = max thực tế × 1.5 (KHÔNG `VARCHAR(255)` cho mọi thứ)
- [ ] Enum status: **TINYINT UNSIGNED** với comment giá trị (KHÔNG VARCHAR) — SCR02.3
- [ ] IPv4: `INT UNSIGNED` (KHÔNG VARCHAR(15))
- [ ] PII column (CCCD, phone, email, account_number): cân nhắc mask + audit (BKM08)
- [ ] ≥3 cột TEXT/LONGTEXT trong 1 bảng → **TÁCH BẢNG RIÊNG** (SCR02.7)

**C. NOT NULL + DEFAULT (SCR05)**:
- [ ] Mọi cột NOT NULL phải có DEFAULT
- [ ] Time NOT NULL: `DEFAULT CURRENT_TIMESTAMP`
- [ ] Numeric NOT NULL: `DEFAULT 0`
- [ ] String NOT NULL: `DEFAULT ''` hoặc bỏ NOT NULL

**D. CHARSET (SCR03)**:
- [ ] Mọi string col: `CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci`

**E. COMMENT (SCR04)**:
- [ ] Mọi cột có comment ý nghĩa (trừ self-explanatory: id, created_at)
- [ ] TINYINT với <10 giá trị: comment liệt kê giá trị (`'0=draft, 1=active, 2=disabled'`)
- [ ] Bảng có comment giải thích role

**F. COLUMN COUNT (SCR06)**:
- [ ] ≤30 cột/bảng. Nếu vượt → tách theo nghiệp vụ

**G. INDEX (IDR01-02)**:
- [ ] Index naming prefix `idx_`, `uk_`, `fk_`
- [ ] PK không cần index riêng
- [ ] Index cho mọi cột trong WHERE/JOIN/ORDER BY thường dùng
- [ ] Composite index ≤5 cột
- [ ] Composite: equality first, range last `(equality_col, range_col)`
- [ ] KHÔNG redundant index (prefix coverage)
- [ ] Index prefix cho VARCHAR dài: chọn độ dài đảm bảo cardinality ≥90%

**H. PARTITION (IDR03 + BKM06)**:
- [ ] Tăng trưởng >5tr rows/tháng → **PHẢI partition**
- [ ] Bảng tên có `_logs`, `_audit`, `_history`, `_events` → **BẮT BUỘC partition** (BKM06)
- [ ] Partition key phải trong PRIMARY KEY: `PRIMARY KEY (id, partition_key)`
- [ ] RANGE by date cho logs: `PARTITION BY RANGE (TO_DAYS(created_at))`
- [ ] Tối thiểu 3tr records/partition, max 2GB hoặc 5tr/partition
- [ ] Pre-create 12 partition cho 1 năm + 1 `pmax` fallback

**I. BAOKIM SPECIFIC (BKM01-08)**:
- [ ] **BKM01: KHÔNG FOREIGN KEY constraint**. Soft ref qua naming + validate ở Service
- [ ] **BKM02: Eloquent only** — không ảnh hưởng schema, nhưng schema phải hỗ trợ Eloquent (vd: dùng `id` primary, không composite cho non-partition table)
- [ ] **BKM06: Log table phải partition**
- [ ] **BKM08: Fintech security checklist**:
  - PII có audit trail? (UPDATE/DELETE log vào audit table?)
  - Soft delete (`deleted_at`)? Mặc định fintech dùng soft delete
  - `created_by`, `updated_by` để trace?
  - PII trong log có mask không?

### Step 3: Generate output 4 phần

**A. Laravel Migration** (cho dev dùng `php artisan migrate`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dùng raw SQL DDL cho MySQL — Schema builder Laravel không support
        // DATETIME ON UPDATE CURRENT_TIMESTAMP + PARTITION + index naming chuẩn.
        DB::statement(<<<'SQL'
            CREATE TABLE [...] (
                -- DDL theo SCR/IDR/BKM
            ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            -- PARTITION nếu cần
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('[table]');
    }
};
```

**B. Raw MySQL DDL** (để paste vào Navicat/CLI nếu cần test trực tiếp):

```sql
CREATE TABLE table_name (...);
```

**C. Decision log** — giải thích từng quyết định kèm rule ID:

| Quyết định | Rule | Lý do |
|---|---|---|
| `created_at DATETIME` (không TIMESTAMP) | SCR02.4 | TIMESTAMP có 2038 problem + timezone conversion |
| Không FK | BKM01 | Performance, validate ở Service |
| `INT UNSIGNED` PK (không BIGINT) | BKM05 | Volume <100M dự kiến |

**D. Pre-merge Checklist** — engineer self-review trước commit:

```markdown
- [ ] Naming snake_case + plural + ≤24 chars
- [ ] PK INT/BIGINT phù hợp volume (BKM05)
- [ ] Time cột DATETIME, không TIMESTAMP (SCR02.4)
- [ ] Tiền cột DECIMAL, không FLOAT (SCR02.5)
- [ ] NOT NULL có DEFAULT (SCR05)
- [ ] ≥3 TEXT cột → đã tách bảng (SCR02.7)
- [ ] utf8mb4_unicode_ci (SCR03)
- [ ] Comment đầy đủ (SCR04)
- [ ] ≤30 cột (SCR06)
- [ ] Index naming idx_/uk_/fk_ (IDR01)
- [ ] Không redundant index (IDR02.1)
- [ ] Composite ≤5 cột, equality first (IDR02.3)
- [ ] Partition nếu >5tr/tháng hoặc log table (IDR03.1, BKM06)
- [ ] KHÔNG FOREIGN KEY (BKM01)
- [ ] PII có mask + audit nếu fintech (BKM08)
```

### Step 4: Self-review trước khi handoff

Sau khi sinh xong, **tự chạy checklist qua skill `dev-baokim-sql-reviewer`** để catch những gì miss. Skill chains thành pipeline: design → self-review → handoff.

## Common traps — Laravel default conflict với Baokim

Đây là **encoded lesson learned** từ dự án OCR Baokim. Tránh các pattern Laravel default sau:

### Trap 1: `$table->timestamps()` ❌ → SCR02.4 violation

**Bad** (Laravel default, vi phạm SCR02.4):
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->timestamps();   // ❌ Tạo created_at TIMESTAMP, updated_at TIMESTAMP
});
```

**Vấn đề**: `$table->timestamps()` Laravel sinh ra cột `TIMESTAMP` — vi phạm SCR02.4 (phải DATETIME) + 2038 problem + timezone conversion.

**Fix** — dùng raw SQL hoặc dateTime() explicit:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->bigIncrements('id');
    // ... business cols
    $table->dateTime('created_at')->useCurrent()
          ->comment('Thời điểm tạo record');
    $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate()
          ->comment('Cập nhật cuối');
});
```

Hoặc raw SQL nếu cần partition + reserved word fix:
```php
DB::statement(<<<'SQL'
    CREATE TABLE orders (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        -- ... business cols
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm tạo',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Cập nhật cuối',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
```

### Trap 2: `$table->foreignId('user_id')->constrained()` ❌ → BKM01 violation

**Bad** (Laravel idiomatic, vi phạm BKM01):
```php
$table->foreignId('user_id')->constrained();   // ❌ Tạo FOREIGN KEY constraint
```

**Fix**:
```php
$table->unsignedBigInteger('user_id')
      ->comment('BKM01 no FK — soft ref users.id, validate ở UserService');
$table->index('user_id', 'idx_user_id');   // Index thay FK
```

### Trap 3: Index naming Laravel default

**Bad** (Laravel sinh tên `<table>_<col>_index`):
```php
$table->index('status');   // → orders_status_index
$table->unique('email');   // → orders_email_unique
```

**Fix** — đặt tên explicit theo prefix BKM:
```php
$table->index('status', 'idx_status');
$table->unique('email', 'uk_email');
$table->index(['user_id', 'created_at'], 'idx_user_created');
```

### Trap 4: `$table->string('status')` cho enum ❌ → SCR02.3

**Bad**:
```php
$table->string('status', 20)->default('active');   // VARCHAR(20) cho 3 giá trị
```

**Fix**:
```php
$table->unsignedTinyInteger('status')->default(1)
      ->comment('1=active, 2=disabled, 3=archived');
```

### Trap 5: Quên partition cho log table

**Bad**:
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    // ... cols
    $table->dateTime('created_at')->useCurrent();
    $table->index('created_at');
});
```

**Fix** — bắt buộc partition cho `*_logs` (BKM06):
```php
DB::statement(<<<'SQL'
    CREATE TABLE audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        -- cols
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id, created_at)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    PARTITION BY RANGE (TO_DAYS(created_at)) (
        PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
        PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
        -- ... 10-12 partition cho 1 năm
        PARTITION pmax VALUES LESS THAN MAXVALUE
    )
SQL);
```

### Trap 6: Tên cột là MySQL reserved word

**Bad**:
```php
$table->string('event', 50);   // ❌ 'event' là reserved word MySQL 8
$table->string('order', 20);   // ❌ 'order' reserved
$table->tinyInteger('status'); // ⚠️ 'status' reserved trong MySQL 8.0.31+
```

**Fix**:
```php
$table->string('event_name', 50);
$table->integer('display_order');
$table->tinyInteger('order_status');
```

Reserved word list MySQL 8: <https://dev.mysql.com/doc/refman/8.0/en/keywords.html>

### Trap 7: ≥3 LONGTEXT trong 1 bảng

**Bad**:
```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->longText('text_full');
    $table->longText('text_masked');
    $table->longText('translation');
    // ... ↑ 3 LONGTEXT → vi phạm SCR02.7
});
```

**Fix** — tách bảng riêng:
```php
// Bảng chính chỉ giữ metadata
Schema::create('documents', function (Blueprint $table) {
    $table->bigIncrements('id');
    // ... metadata cols
    $table->dateTime('created_at')->useCurrent();
});

// Bảng phụ chứa text (1-1 qua UNIQUE KEY)
Schema::create('document_texts', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('document_id')
          ->comment('BKM01 no FK — soft ref documents.id');
    $table->longText('text_full')->nullable();
    $table->longText('text_masked')->nullable();
    $table->longText('translation')->nullable();
    $table->dateTime('created_at')->useCurrent();
    $table->unique('document_id', 'uk_document_id');
});
```

### Trap 8: `$table->id()` cho mọi PK ❌ → BKM05

**Bad** (mặc định BIGINT cho mọi bảng):
```php
$table->id();   // BIGINT UNSIGNED — overkill cho bảng nhỏ
```

**Fix** — chọn type theo volume:
```php
// Bảng master/config (<10k rows)
$table->smallIncrements('id');   // SMALLINT UNSIGNED

// Bảng entity thông thường (<100M rows)
$table->increments('id');   // INT UNSIGNED

// Bảng transaction/log/audit (>100M dự kiến)
$table->bigIncrements('id');   // BIGINT UNSIGNED
```

## Examples

### Example 1: Design bảng `merchant_payouts`

**Input**:
> "Thiết kế bảng `merchant_payouts` lưu khoản chi trả cho merchant: số tiền, ngân hàng, trạng thái xử lý. Volume ~10k/ngày, ~3M/năm."

**Skill output**:

```markdown
## Decision log

| Quyết định | Rule | Lý do |
|---|---|---|
| Table name `merchant_payouts` (plural) | SCR01.2 | Số nhiều, 2 words, 16 chars OK |
| PK `INT UNSIGNED` | BKM05 | 3M/năm × 100 năm = 300M < 4.2 tỷ — INT đủ |
| `amount DECIMAL(18,2)` | SCR02.5 | Tiền — bắt buộc DECIMAL |
| `merchant_id INT UNSIGNED` (no FK) | BKM01 + BKM05 | Soft ref + match `merchants.id` type |
| `bank_account_no CHAR(20)` (PII!) | SCR02.6 + BKM08 | Fixed length 14-20 → CHAR. Mask trong audit. |
| `status TINYINT UNSIGNED` | SCR02.3 | 5 trạng thái → TINYINT + comment values |
| `created_at DATETIME` | SCR02.4 | Không TIMESTAMP |
| Index `(merchant_id, status)` | IDR | Query phổ biến: tìm payout theo merchant + lọc status |
| Không partition | IDR03 | 3M/năm × 1000 năm mới = 3 tỷ, đủ partition sau |
```

**Migration**:
```php
return new class extends Migration {
    public function up(): void {
        DB::statement(<<<'SQL'
            CREATE TABLE merchant_payouts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                merchant_id INT UNSIGNED NOT NULL COMMENT 'BKM01 no FK — soft ref merchants.id',
                amount DECIMAL(18,2) NOT NULL COMMENT 'Số tiền VND',
                bank_account_no CHAR(20) NOT NULL COMMENT 'Số TK ngân hàng — mask trong audit (BKM08 PII)',
                bank_code VARCHAR(10) NOT NULL COMMENT 'Mã ngân hàng VCB|TCB|MB|...',
                status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=pending, 2=processing, 3=success, 4=failed, 5=cancelled',
                processed_at DATETIME NULL COMMENT 'Thời điểm xử lý',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_merchant_status (merchant_id, status),
                KEY idx_status_created (status, created_at)
            ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Khoản chi trả cho merchant'
        SQL);
    }
    public function down(): void {
        Schema::dropIfExists('merchant_payouts');
    }
};
```

### Example 2: Design `transaction_logs` — log table cần partition

**Input**:
> "Lưu log mọi transaction đi qua hệ thống. Volume ~50M/tháng. Cần truy lại theo `merchant_id` và `tx_code`."

**Skill output highlights**:

- Volume 50M/tháng → **PHẢI partition** (IDR03.1 + BKM06)
- PK `BIGINT UNSIGNED` (BKM05 — >2B rows dự kiến trong 5 năm)
- Composite PRIMARY KEY `(id, created_at)` để partition

```php
DB::statement(<<<'SQL'
    CREATE TABLE transaction_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        merchant_id INT UNSIGNED NOT NULL COMMENT 'BKM01 no FK',
        tx_code CHAR(16) NOT NULL COMMENT 'Transaction code, format BK-XXXXX',
        action_name VARCHAR(50) NOT NULL COMMENT 'Loại hành động: created|paid|refunded|cancelled',
        amount DECIMAL(18,2) NULL COMMENT 'VND',
        request_ip INT UNSIGNED NULL COMMENT 'IPv4 INT UNSIGNED — SCR02.3',
        payload JSON NULL COMMENT 'Snapshot data',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id, created_at),
        KEY idx_merchant_created (merchant_id, created_at),
        KEY idx_tx_code (tx_code)
    ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Log transaction — BKM06 partition monthly, retention 5y TT39/2016'
    PARTITION BY RANGE (TO_DAYS(created_at)) (
        PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
        PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
        PARTITION p202603 VALUES LESS THAN (TO_DAYS('2026-04-01')),
        PARTITION p202604 VALUES LESS THAN (TO_DAYS('2026-05-01')),
        PARTITION p202605 VALUES LESS THAN (TO_DAYS('2026-06-01')),
        PARTITION p202606 VALUES LESS THAN (TO_DAYS('2026-07-01')),
        PARTITION p202607 VALUES LESS THAN (TO_DAYS('2026-08-01')),
        PARTITION p202608 VALUES LESS THAN (TO_DAYS('2026-09-01')),
        PARTITION p202609 VALUES LESS THAN (TO_DAYS('2026-10-01')),
        PARTITION p202610 VALUES LESS THAN (TO_DAYS('2026-11-01')),
        PARTITION p202611 VALUES LESS THAN (TO_DAYS('2026-12-01')),
        PARTITION p202612 VALUES LESS THAN (TO_DAYS('2027-01-01')),
        PARTITION pmax VALUES LESS THAN MAXVALUE
    )
SQL);
```

### Example 3: Refactor — tách LONGTEXT theo SCR02.7

**Input**:
> "Hiện bảng `articles` có id, title, slug, content LONGTEXT, content_html LONGTEXT, content_markdown LONGTEXT, ... Nâng cấp theo Baokim chuẩn."

**Skill output**:

- Phát hiện 3 LONGTEXT trong 1 bảng → vi phạm SCR02.7 → **tách bảng**
- Bảng `articles` giữ metadata, tạo `article_texts` 1-1 chứa LONGTEXT

```sql
-- Bảng metadata
CREATE TABLE articles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    author_id INT UNSIGNED NOT NULL COMMENT 'BKM01 no FK',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=draft, 2=published, 3=archived',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug),
    KEY idx_author_status (author_id, status)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng text (tách theo SCR02.7)
CREATE TABLE article_texts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL COMMENT 'BKM01 no FK',
    content LONGTEXT NULL,
    content_html LONGTEXT NULL,
    content_markdown LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_article_id (article_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## What NOT to do

❌ KHÔNG dùng `$table->timestamps()` — sinh TIMESTAMP, vi phạm SCR02.4
❌ KHÔNG dùng `$table->foreignId()->constrained()` — sinh FK, vi phạm BKM01
❌ KHÔNG dùng `$table->string('status', 20)` cho enum 3-5 giá trị — vi phạm SCR02.3
❌ KHÔNG dùng `$table->id()` mặc định BIGINT cho mọi bảng — overkill, vi phạm BKM05
❌ KHÔNG quên partition cho `*_logs`, `*_history`, `*_events` — vi phạm BKM06
❌ KHÔNG đặt tên cột là MySQL reserved word (`event`, `order`, `desc`, `range`, `key`, `group`) — vi phạm SCR01.4
❌ KHÔNG để ≥3 LONGTEXT/TEXT trong 1 bảng — vi phạm SCR02.7
❌ KHÔNG dùng `FLOAT/DOUBLE` cho tiền — vi phạm SCR02.5 (fintech mandatory)
❌ KHÔNG để `NOT NULL` thiếu DEFAULT — vi phạm SCR05
❌ KHÔNG sinh migration chỉ với Schema builder cho partition table — Schema builder không hỗ trợ PARTITION BY → phải dùng raw `DB::statement(...)`

## Reference foundation

- `.claude/references/baokim-db-standard.md` — full rule definitions
- `dev-baokim-sql-reviewer` — review existing schema (chain với skill này: design → review)
- `dev-baokim-laravel-repo-pattern` — code Repository/Service trên schema

## Maintenance & Roadmap

- v0.1.0 (current): cover 8 nhóm rule SCR/IDR/BKM. Lesson learned từ OCR Baokim project.
- v0.2.0: tích hợp với `php artisan baokim:audit-schema` CLI command tự check vi phạm trên migration mới
- v0.3.0: thêm template cho 5 entity pattern phổ biến: user, transaction, audit_log, lookup_table, junction_table
- v1.0.0: production-tested với 20+ migrations qua review

Tests: `tests/prompts.md`
