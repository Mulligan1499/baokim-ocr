# Database Testing — Checklist & Strategy

Áp dụng cho RDBMS (MySQL, PostgreSQL, SQL Server, Oracle), NoSQL (MongoDB, DynamoDB), data warehouse (Snowflake, BigQuery, Redshift).

QA database không phải DBA — không cần tối ưu index. QA database tập trung vào: **data integrity, correctness sau CRUD, migration safety, dữ liệu sau pipeline ETL có đúng không**.

---

## 1. Tại sao test ở tầng DB là cần thiết?

UI test có thể pass mà data trong DB vẫn sai — ví dụ:
- UI hiển thị "Saved" nhưng record chưa thực sự commit (lost update do race).
- UI delete xong, record bị soft delete nhưng vẫn còn relation, gây orphan.
- UI hiển thị danh sách 10 item nhưng trong DB có 11 (sót do filter sai).
- API trả 201 nhưng audit log không ghi (trigger fail âm thầm).

→ Test DB **bổ sung** cho UI/API test, không thay thế.

---

## 2. Schema & Constraint Testing

### 2.1 Bắt buộc check:

| Loại | Test |
|---|---|
| **NOT NULL** | INSERT với field NULL → fail |
| **UNIQUE** | INSERT trùng → fail (duplicate key) |
| **PRIMARY KEY** | Không trùng, không null |
| **FOREIGN KEY** | INSERT với FK không tồn tại → fail; DELETE cha có con → fail (nếu RESTRICT) hoặc cascade đúng (nếu CASCADE) |
| **CHECK** | INSERT vi phạm rule (vd `age >= 0`) → fail |
| **DEFAULT** | INSERT không gửi field → giá trị default đúng |
| **Data type** | INSERT số vào field date → fail |
| **Length** | INSERT chuỗi dài hơn varchar(N) → fail hoặc truncate (theo strict mode) |
| **Charset/Collation** | INSERT emoji 🎉, tiếng Việt có dấu → lưu và đọc lại đúng |

### 2.2 Encoding & Collation:
- DB dùng `utf8` (3 byte) hay `utf8mb4` (4 byte, hỗ trợ emoji)? Test với emoji.
- Tiếng Việt: lưu "Nguyễn Văn Sơn" → đọc ra đúng dấu, không bị `?`, không thành "Nguyen Van Son".
- Sort/compare có case-sensitive không (collation `_ci` vs `_cs`).
- Tìm kiếm có dấu vs không dấu (`Sơn` có match khi search `Son`?).

---

## 3. CRUD Operation Testing

Cho mỗi bảng quan trọng, test 4 thao tác:

### 3.1 CREATE (INSERT)
- Insert record với đầy đủ field hợp lệ → record xuất hiện trong DB.
- `created_at`, `updated_at` được set tự động (trigger hoặc default).
- ID auto-increment đúng (không skip nhiều, không trùng sau restart).
- Bulk insert: 1000 record cùng lúc → tất cả ghi vào.
- Insert rồi rollback transaction → record không còn.

### 3.2 READ (SELECT)
- Filter đúng (WHERE).
- JOIN đúng — đặc biệt LEFT JOIN có NULL ở bảng phải.
- Sort đúng (ORDER BY).
- LIMIT/OFFSET đúng.
- Aggregation: COUNT, SUM, AVG đúng (đặc biệt khi có NULL — `COUNT(*)` vs `COUNT(field)`).
- Pagination consistent: page 1 + page 2 không trùng row, không thiếu row.

### 3.3 UPDATE
- Update record đúng → field thay đổi, các field khác giữ nguyên.
- `updated_at` được cập nhật.
- Update với WHERE không match → 0 row affected, không báo lỗi false-success.
- Concurrent update: 2 transaction cùng update → optimistic lock (version field) hay last-write-wins.
- **Bug điển hình**: UPDATE thiếu WHERE → update toàn bảng (test review SQL trước khi chạy production).

### 3.4 DELETE
- Hard delete: record biến mất.
- Soft delete: record còn nhưng `deleted_at` được set; query mặc định không trả về.
- Cascade delete: xóa parent → children xóa theo (nếu thiết kế vậy).
- Restrict delete: xóa parent đang có children → bị từ chối.
- Trigger delete: ghi audit log đúng.
- **Bug điển hình**: app dùng soft delete nhưng query analytics quên filter `deleted_at IS NULL` → đếm sai.

---

## 4. Transaction & ACID Testing

### 4.1 Atomicity:
- Transaction gồm 2 INSERT — INSERT thứ 2 fail → INSERT thứ nhất rollback.
- Test: ngắt connection giữa transaction → state DB consistent (không có half-committed data).

### 4.2 Consistency:
- Mọi constraint vẫn được giữ sau transaction.
- Trigger chạy đúng và không tạo state không hợp lệ.

### 4.3 Isolation:
- Test isolation level đang dùng (READ COMMITTED, REPEATABLE READ, SERIALIZABLE).
- **Dirty read**: T1 update chưa commit → T2 đọc thấy không?
- **Non-repeatable read**: T1 đọc 2 lần → giá trị có thay đổi giữa chừng do T2 commit?
- **Phantom read**: T1 SELECT ra 5 row, T2 INSERT thêm, T1 SELECT lại → bao nhiêu row?
- **Lost update**: 2 user cùng update từ giá trị X → ai thắng.

### 4.4 Durability:
- Commit xong → kill DB → restart → data còn.
- WAL (Write-Ahead Log) hoạt động đúng.

---

## 5. Migration Testing — quan trọng nhất khi release

Mỗi schema migration đều là **rủi ro cao**. Bắt buộc test:

### 5.1 Forward migration:
- Apply migration trên DB có **dữ liệu thực** (clone từ production sau khi mask) — không phải DB rỗng.
- Verify schema thay đổi đúng (column mới, index mới, constraint mới).
- Verify dữ liệu hiện có không mất, không bị corrupt.
- Verify dữ liệu mới (nếu có script backfill) đúng logic.
- Đo thời gian migration — migration chạy 30 phút trên production có acceptable không?
- Lock table khi migration: app có downtime hay không?

### 5.2 Rollback migration:
- Migration có rollback script không? (BẮT BUỘC có).
- Apply forward → rollback → schema và data như cũ (idempotent).
- Một số thay đổi không rollback được (xóa column → mất data) → migration phải multi-phase.

### 5.3 Multi-phase migration (zero-downtime):
- Phase 1: thêm column mới (nullable), không xóa gì.
- Phase 2: deploy code mới ghi cả 2 column (cũ + mới).
- Phase 3: backfill data cho column mới.
- Phase 4: deploy code chỉ đọc column mới.
- Phase 5: xóa column cũ.

→ QA test mỗi phase **độc lập** + test rollback ở mỗi phase.

### 5.4 Backfill testing:
- Script backfill có **idempotent** không (chạy 2 lần kết quả giống nhau).
- Có **resume** được không nếu fail giữa chừng.
- Performance trên data lớn (10M, 100M record).
- Có log progress để monitor.

---

## 6. Data Integrity Testing — sau khi feature hoạt động

### 6.1 Referential integrity:
```sql
-- Tìm orphan: order trỏ vào user không tồn tại
SELECT o.id FROM orders o
LEFT JOIN users u ON o.user_id = u.id
WHERE u.id IS NULL;
-- Kỳ vọng: 0 row
```

### 6.2 Logical integrity:
```sql
-- Order amount phải = sum(line_items)
SELECT o.id, o.total, SUM(li.price * li.qty) AS calc
FROM orders o
JOIN order_items li ON li.order_id = o.id
GROUP BY o.id, o.total
HAVING o.total <> SUM(li.price * li.qty);
-- Kỳ vọng: 0 row
```

### 6.3 Duplicate detection:
```sql
SELECT email, COUNT(*) FROM users GROUP BY email HAVING COUNT(*) > 1;
```

### 6.4 Audit consistency:
- Mọi UPDATE quan trọng có ghi audit log không? `users` có 100 update mà `audit_log` chỉ có 95 → bug.

### 6.5 Data freshness:
- `updated_at` của report data có update theo job?
- Cache table có sync với source table không?

---

## 7. Performance Testing tầng DB

QA phối hợp với DBA. Trọng tâm QA:

- **Slow query**: bật slow query log, identify query > 1s.
- **N+1 query**: monitor query count cho 1 API call — không tăng theo số record.
- **Index usage**: `EXPLAIN` cho query critical — có dùng index không, có full table scan không.
- **Connection pool exhaustion**: load test → connection pool overflow?
- **Lock contention**: concurrent update cùng row → deadlock?
- **Storage growth**: bảng tăng theo dự đoán không, có cần partition không.

---

## 8. NoSQL Specific

### 8.1 MongoDB:
- Schema flexibility — test field có/không có ở các document.
- Aggregation pipeline đúng kết quả.
- Index TTL: document tự xóa sau N giây.
- Replica set: write concern, read preference.
- Transaction (MongoDB 4.0+): tương tự RDBMS.

### 8.2 DynamoDB:
- Partition key & sort key thiết kế đúng cho query pattern.
- Query vs Scan: dùng Scan trên bảng lớn → tốn cost + chậm.
- GSI (Global Secondary Index) consistency: eventually consistent.
- Conditional write: optimistic lock qua attribute.
- Hot partition: load test phát hiện partition bị throttle.

### 8.3 Redis:
- TTL của key.
- Eviction policy khi đầy memory.
- Persistence: RDB snapshot, AOF log.
- Pub/sub message không deliver khi consumer offline.

---

## 9. Data Warehouse / ETL Testing

Áp dụng cho pipeline data sang BigQuery, Snowflake, Redshift.

### 9.1 Source-to-target test:
- Row count source = row count target (sau khi tính filter).
- Sum/avg các field numeric khớp.
- Distinct value khớp (vd cùng list status).
- Sample 100 row random — so sánh từng field.

### 9.2 Transformation test:
- Logic transform (vd `full_name = first + ' ' + last`) đúng cho mọi case (NULL, empty, special char).
- Type cast: string → number, đặc biệt với data lỗi ("N/A", "").
- Date timezone convert.
- Aggregation đúng.

### 9.3 SCD (Slowly Changing Dimension):
- Type 1 (overwrite): record cũ bị thay thế.
- Type 2 (history): version mới + close version cũ với `valid_to`.
- Test thay đổi 1 dimension → đúng version, đúng history.

### 9.4 Incremental load:
- Lần đầu load full → đúng.
- Lần 2 load incremental (theo `updated_at`) → chỉ load record thay đổi.
- Edge case: record sửa rồi sửa lại trong cùng batch → version cuối cùng đúng.
- Late-arriving data: record cũ insert vào sau → có được pick up không.

### 9.5 Data quality rule:
- Null check trên field bắt buộc.
- Range check (số dương, ngày trong tương lai).
- Format check (email regex).
- Referential check giữa fact & dimension.
- **Tool**: Great Expectations, dbt tests, Monte Carlo, Soda.

---

## 10. Security DB

- **PII**: trường nhạy cảm (CCCD, credit card, password) phải được hash/encrypt — query thử trên DB raw để verify.
- **Password**: lưu hash (bcrypt, argon2), không phải plaintext, không phải MD5/SHA1.
- **Backup encryption**: backup file có encrypted không.
- **Access control DB**: app user có quyền tối thiểu cần thiết, không phải root/sa.
- **SQL injection**: dù app có ORM, vẫn test các input cố tình injection (vd search field) qua API.
- **Audit log**: ai truy cập, ai sửa data nhạy cảm — có log không.

---

## 11. Tools đề xuất

| Loại | Tool |
|---|---|
| SQL client | DBeaver, DataGrip, TablePlus, pgAdmin, MySQL Workbench |
| Migration | Flyway, Liquibase, Alembic, Knex, Prisma Migrate |
| Data quality | Great Expectations, Soda, dbt tests, Monte Carlo |
| Test data generation | Faker, Mockaroo, Synth |
| ETL test | dbt + dbt-expectations, Apache Beam Direct Runner |
| Load test | sysbench, pgbench, mongoperf |
| Schema diff | Liquibase diff, redgate SQL Compare, Atlas |
| NoSQL viewer | Studio 3T, NoSQLBooster, DynamoDB workbench |

---

## 12. Bug điển hình DB (luôn check)

1. Constraint thiếu → data invalid lọt vào (email không format, status không thuộc enum).
2. Index thiếu → query chậm khi data lớn.
3. Soft delete không filter trong query → đếm sai, hiển thị data đã xóa.
4. `updated_at` không cập nhật khi UPDATE.
5. Cascade delete xóa data quan trọng vô tình (phải dùng RESTRICT cho data nhạy cảm).
6. Migration không rollback được trên production có data.
7. Backfill script chạy lại không idempotent → duplicate.
8. Timezone trong DB không nhất quán (UTC vs local).
9. Decimal field cho tiền tệ dùng FLOAT thay vì DECIMAL → sai lệch.
10. Encoding sai → tiếng Việt/emoji thành `?`.
11. Charset DB và app không khớp → data ghi đúng nhưng đọc ra sai.
12. Connection leak → pool cạn sau vài giờ.
13. Transaction không rollback khi exception → data inconsistent.
14. Trigger gọi đệ quy → infinite loop hoặc stack overflow.
15. Statistics outdated → query plan tệ → bất ngờ chậm sau N tháng.
