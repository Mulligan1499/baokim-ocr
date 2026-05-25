# Security Injection Tests — Deep Coverage

Bộ test case chuyên sâu về injection attacks: SQL, NoSQL, Command, Header, JSON parser, JWT bypass, SSRF, XXE, Mass assignment, Resource exhaustion.

> Dùng kèm với `api-spec-to-testcase.md` (OWASP API Top 10 cơ bản) và `api-field-validation-pattern.md` (validate per field).

---

## 1. Khi nào dùng

Áp dụng khi:
- API xử lý dữ liệu nhạy cảm (payment, financial, PII, healthcare)
- API có authentication/authorization phức tạp
- Sản phẩm có yêu cầu compliance: PCI-DSS, SOC 2, ISO 27001
- Penetration test trước khi go-live
- Sau khi đã có WAF/SIEM cần verify defense in depth ở application layer

KHÔNG cần áp dụng đầy đủ cho:
- Internal admin tool chỉ có vài user
- Prototype/POC
- Smoke test daily

---

## 2. SQL Injection — 6 kỹ thuật cần cover

### 2.1 Boolean-based SQL injection

**Payload**: `' OR '1'='1` hoặc `' OR 1=1 --`

**Mục đích**: bypass authentication, lấy tất cả record.

**Test case**:
```
Test Item:   KT <field> chứa SQL injection (boolean-based)
Data Test:   field = "' OR '1'='1"
Step:        1. Gửi payload\n2. Verify response + log
Expected:    - code = 422/401\n- KHÔNG bypass authentication\n- KHÔNG trả 500, KHÔNG lộ stack trace\n- Log SIEM ghi nhận
Priority:    High
```

**Cách verify**: response giống case input invalid bình thường (không khác biệt với "không tồn tại"). Nếu khác → vulnerable.

### 2.2 Comment-based SQL injection

**Payload**: `admin' --` hoặc `admin' #`

**Mục đích**: bỏ qua phần WHERE password = ?

**Test case**:
```
Test Item:   KT <field> chứa SQL injection (comment-based)
Data Test:   field = "admin' -- "
Expected:    - code = 422/401\n- KHÔNG bypass authentication
```

### 2.3 UNION-based SQL injection — leak data

**Payload tăng dần**:
```
Bước 1: ' UNION SELECT NULL--
Bước 2: ' UNION SELECT NULL,NULL--
Bước 3: ' UNION SELECT NULL,NULL,NULL--   (tìm số cột đúng)
Bước 4: ' UNION SELECT username,password,NULL FROM users--
```

**Test case**:
```
Test Item:   KT <field> chứa UNION-based SQL injection
Data Test:   field = "' UNION SELECT NULL,NULL,NULL-- "
             Sau đó tăng dần số NULL, cuối:
             field = "' UNION SELECT client_secret,NULL,NULL FROM merchants-- "
Step:        1. Gửi từng payload\n2. Phân tích response
Expected:    - code = 422/104\n- Response KHÔNG chứa data từ table khác\n- Không có chuỗi giống client_secret/password trong response
Priority:    High
```

### 2.4 Stacked queries — DROP/DELETE bypass

**Payload**: `'; DROP TABLE x; -- ` (PostgreSQL/SQL Server) hoặc `'; DELETE FROM x WHERE 1=1; -- ` (MySQL với multi_query enabled)

**Test case**:
```
Test Item:   KT <field> chứa stacked SQL injection
Data Test:   field = "X'; DROP TABLE <bảng_test>; -- "
Pre-Cond:    Đếm record các bảng trước test (COUNT(*))
Step:        1. Snapshot COUNT(*) tables\n2. Gửi payload\n3. Verify COUNT(*) sau test
Expected:    - code = 422\n- COUNT(*) tất cả tables KHÔNG thay đổi\n- KHÔNG có DROP/DELETE được thực thi
Priority:    High
```

### 2.5 Time-based blind SQL injection

**Payload**:
- MySQL: `' AND (SELECT SLEEP(10)) -- `
- SQL Server: `'; WAITFOR DELAY '0:0:10' -- `
- PostgreSQL: `'; SELECT pg_sleep(10) -- `

**Test case**:
```
Test Item:   KT Time-based blind SQL injection trên <field>
Data Test:   field = "X' AND (SELECT SLEEP(10))-- " (MySQL)
             field = "X'; WAITFOR DELAY '0:0:10'-- " (SQL Server)
Step:        1. Ghi nhận T1 = now\n2. Gửi payload\n3. Ghi nhận T2 = response time\n4. Tính diff = T2 - T1
Expected:    - diff < 2s (không bị delay)\n- code = 422/104\n- Nếu diff > 5s → CRITICAL BUG: vulnerable to time-based SQLi
Priority:    High
```

### 2.6 Error-based SQL injection — extract DB version

**Payload**:
- MySQL: `' AND extractvalue(1,concat(0x7e,version())) -- `
- SQL Server: `' AND 1=convert(int,@@version) -- `

**Test case**:
```
Test Item:   KT Error-based SQL injection — extract DB info
Data Test:   field = "' AND extractvalue(1,concat(0x7e,version()))-- "
Expected:    - Response error message KHÔNG chứa version DB\n- KHÔNG leak SQL syntax\n- Generic error message
Priority:    High
```

### 2.7 Second-order SQL injection (stored payload)

Payload không trigger ngay khi insert, chỉ trigger khi đọc lại.

**Test case**:
```
Test Item:   KT Second-order SQL injection (stored payload)
Data Test:   Bước 1: Tạo record với payload trong field text:
                 card_name = "NGUYEN VAN A'; -- "
             Bước 2: Trigger API khác query record này
                 (vd: GET /reports, API search, dashboard)
Step:        1. Tạo record với payload\n2. Verify record được lưu (escape)\n3. Trigger API đọc lại\n4. Verify response và DB
Expected:    - Record lưu raw payload (escape khi insert)\n- Khi đọc lại: KHÔNG có SQL injection thực thi\n- Response không lộ data từ table khác
Priority:    High
```

---

## 3. NoSQL Injection (MongoDB-style)

### 3.1 Operator injection

**Payload**: `{"$ne": null}` hoặc `{"$gt": ""}`

**Mục đích**: bypass authentication bằng cách thay value bằng operator.

**Test case**:
```
Test Item:   KT NoSQL injection — operator injection
Data Test:   Body:
             {
               "username": {"$ne": null},
               "password": {"$ne": null}
             }
Expected:    - code = 422 (validate kiểu string)\n- KHÔNG bypass authentication\n- KHÔNG cấp token
Priority:    High
```

### 3.2 JavaScript injection (MongoDB `$where`)

**Payload**: `'; return true; //`

**Test case**:
```
Test Item:   KT NoSQL injection — JavaScript injection
Data Test:   field = "X'; return true; //"
Expected:    - code = 422\n- KHÔNG execute JS code\n- KHÔNG bypass authentication
Priority:    Medium
```

---

## 4. OS Command Injection

**Payload các loại**:
- Unix: `; ls -la`, `| cat /etc/passwd`, `$(whoami)`, `` `id` ``
- Windows: `& dir`, `&& whoami`, `| cmd`

**Test case**:
```
Test Item:   KT OS Command injection trên các text field
Data Test:   Test các field input:
             a. field = "value; ls -la"
             b. field = "value$(whoami)"
             c. field = "value`id`"
             d. field = "value | cat /etc/passwd"
Step:        1. Gửi từng payload\n2. Verify response và log
Expected:    - code = 422\n- KHÔNG execute OS command\n- Response không chứa output của command (ls, whoami...)
Priority:    High
```

---

## 5. Header Injection

### 5.1 CRLF injection

**Payload**: `value\r\nX-Injected: malicious` hoặc `value%0d%0aX-Injected: malicious`

**Test case**:
```
Test Item:   KT HTTP Header injection (CRLF injection)
Data Test:   Header: Authorization: Bearer <JWT>\r\nX-Injected: malicious
             Hoặc body:
             url_success = "https://merchant.com/\r\nLocation: https://evil.com"
Expected:    - Response headers KHÔNG có X-Injected\n- KHÔNG có Location redirect tới evil.com\n- Hệ thống strip/reject CRLF
Priority:    High
```

### 5.2 Host Header injection

**Payload**: Sửa `Host` header thành attacker-controlled domain.

**Test case**:
```
Test Item:   KT Host Header injection
Data Test:   Headers:
             - Host: evil.com
             - X-Forwarded-Host: evil.com
Expected:    - API hoạt động bình thường\n- KHÔNG generate URL chứa evil.com (vd password reset link)\n- Server verify Host whitelist
Priority:    Medium
```

---

## 6. JSON Parser Confusion

### 6.1 Unicode escape — Null byte / RTL override

**Payload**: `\u0000` (null byte), `\u202e\u202d` (RTL override)

**Test case**:
```
Test Item:   KT JSON injection — Unicode escape
Data Test:   Body:
             {
               "request_id": "MRC\u0000_001",
               "card_name": "A\u202e\u202d"
             }
Expected:    - code = 422 hoặc strip Unicode đặc biệt\n- KHÔNG crash backend\n- KHÔNG lưu raw bytes nguy hiểm vào DB
Priority:    Medium
```

### 6.2 Duplicate keys

**Test case**:
```
Test Item:   KT JSON parser confusion — duplicate keys
Data Test:   Body:
             {
               "amount": 1000,
               "amount": 100000000   ← duplicate
             }
Step:        1. Gửi body có duplicate amount\n2. Verify amount nào được lưu
Expected:    Confirm BA behavior:
             - RFC: last-wins → DB lưu 100000000
             - Hoặc reject → 422
             - KHÔNG được xử lý bằng amount đầu (1000) nhưng lưu amount sau
Priority:    High
```

**Lý do**: nhiều framework parser tham số kiểu khác nhau (Express: last-wins, một số: first-wins). Nếu validation chạy trên amount đầu nhưng business logic chạy trên amount sau → bug bảo mật nghiêm trọng.

---

## 7. JWT Authentication Bypass

### 7.1 Algorithm confusion — `alg: none`

**Test case**:
```
Test Item:   KT JWT algorithm confusion (alg: none)
Data Test:   1. Decode JWT hợp lệ
             2. Sửa header: {"alg": "none", "typ": "JWT"}
             3. Re-encode, bỏ phần signature
             4. Authorization: Bearer <header>.<payload>.
Expected:    - code = 104/401\n- KHÔNG accept alg=none\n- Nếu accept → CRITICAL BUG
Priority:    High
```

### 7.2 Signature stripping

**Test case**:
```
Test Item:   KT JWT signature stripping
Data Test:   JWT format: header.payload.signature
             Gửi: Bearer <header>.<payload>  (bỏ signature)
Expected:    - code = 104/401\n- Reject JWT không signature
Priority:    High
```

### 7.3 JWT bị tampered

**Test case**:
```
Test Item:   KT JWT bị tampered (sửa payload, giữ signature cũ)
Data Test:   1. Decode JWT
             2. Sửa payload (vd: merchant_info.code → merchant khác)
             3. Re-encode payload, GIỮ NGUYÊN signature gốc
             4. Bearer <tampered_JWT>
Expected:    code = 104, signature verify fail
Priority:    High
```

### 7.4 Algorithm substitution — RS256 → HS256

Nếu API dùng RS256 (public/private key), thử đổi sang HS256 và dùng public key làm secret để sign.

```
Test Item:   KT JWT algorithm substitution (RS256 → HS256)
Data Test:   1. Lấy public key (thường công khai)
             2. Decode JWT, sửa alg = "HS256"
             3. Re-sign bằng public key (làm secret)
Expected:    Server verify alg whitelist (chỉ chấp nhận alg đã config)
             → code = 104
Priority:    High
```

---

## 8. Mass Assignment

API nhận body chỉ có vài field hợp lệ. Attacker gửi thêm field nhạy cảm để thử inject vào DB.

**Test case**:
```
Test Item:   KT Mass assignment — gửi field không có trong spec
Data Test:   Body bổ sung field không có trong spec:
             {
               ...các field hợp lệ,
               "is_admin": true,
               "user_role": "ADMIN",
               "fee_override": 0,
               "status": "approved",
               "merchant_id": <id của merchant khác>
             }
Step:        1. Gửi body có field thừa\n2. Query DB
Expected:    - API ignore field không có trong whitelist\n- DB KHÔNG bị set is_admin, role, fee_override\n- Nếu DB bị thay đổi → CRITICAL BUG (mass assignment)
Priority:    High
```

---

## 9. XXE (XML External Entity)

Chỉ áp dụng nếu API có accept `application/xml` hoặc parse XML.

**Test case**:
```
Test Item:   KT XXE injection — Content-Type: application/xml
Data Test:   Header Content-Type: application/xml

             Body XML với XXE:
             <?xml version="1.0"?>
             <!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
             <request>
               <field>&xxe;</field>
             </request>
Expected:    - code = 415 (Unsupported Media Type) hoặc 422\n- Response KHÔNG chứa nội dung /etc/passwd\n- API chỉ accept application/json
Priority:    Medium
```

---

## 10. SSRF (Server-Side Request Forgery)

API nhận URL từ user và gọi URL đó.

**Test case**:
```
Test Item:   KT SSRF qua field nhận URL
Data Test:   Test các URL internal:
             a. url = "http://localhost:6379/INFO"  (Redis)
             b. url = "http://169.254.169.254/latest/meta-data/"  (AWS metadata)
             c. url = "http://internal-db:3306/"  (internal DB)
             d. url = "file:///etc/passwd"
             e. url = "gopher://internal-host:port/_..."
Step:        1. Gửi từng URL internal\n2. Verify
Expected:    - code = 422 (URL không thuộc whitelist)\n- Hệ thống KHÔNG follow URL internal\n- KHÔNG leak metadata từ AWS/internal services
Priority:    High
```

---

## 11. Resource Exhaustion / DoS

### 11.1 Rate limit flood

**Test case**:
```
Test Item:   KT Rate limit — flood request hợp lệ
Data Test:   Gửi 100 request /api/endpoint trong 10s
             (mỗi request_id unique để không bị reject vì duplicate)
Step:        1. Loop 100 lần (parallel hoặc sequential)\n2. Đếm response code\n3. Verify rate limit
Expected:    - Có rate limit kích hoạt sau N request\n- Response code = 429 'Too Many Requests'\n- Nếu KHÔNG có → ĐỀ XUẤT bổ sung rate limit per tenant/IP
Priority:    High
```

### 11.2 Payload exhaustion — body quá lớn

**Test case**:
```
Test Item:   KT Resource exhaustion — payload quá lớn
Data Test:   Body với field cực lớn:
             field = "A" * 1_000_000  (1MB)
             Hoặc body 10MB
Expected:    - code = 413 'Payload Too Large' hoặc 422\n- API KHÔNG crash, KHÔNG memory exhaust\n- Có giới hạn body size (vd 1MB)
Priority:    Medium
```

### 11.3 ReDoS (Regex DoS)

Nếu API có field match regex phức tạp.

```
Test Item:   KT ReDoS (Regex Denial of Service)
Data Test:   email = "a" * 30 + "!"   (cho regex email phức tạp)
             Hoặc string trigger catastrophic backtracking
Expected:    - Response time < 2s\n- Nếu > 5s → vulnerable to ReDoS
Priority:    Medium
```

---

## 12. Replay Attack

**Test case**:
```
Test Item:   KT Replay attack — gửi lại CÙNG request 2 lần
Data Test:   Capture full request 1 (headers + body + signature) → replay 5 phút sau
Pre-Cond:    Đã có request thành công với request_id = X
Step:        1. Gửi request 1, lưu nguyên\n2. Đợi 5 phút\n3. Replay đúng request\n4. Verify
Expected:    - code = 103/409 'request_id bị trùng lặp'\n- Không tạo bản ghi mới\n- Không gọi sang downstream
Priority:    High
```

---

## 13. Section template cho file Excel

Khi generate file Excel với section "Security Injection Deep Tests", dùng template sau:

```python
{
    "name": "VI. Security Injection Deep Tests",
    "test_cases": [
        # SQL Injection (6 case)
        {"test_item": "KT Time-based blind SQL injection trên <field>", ...},
        {"test_item": "KT Error-based SQL injection — extract DB version", ...},
        {"test_item": "KT Boolean-based blind SQL injection", ...},
        {"test_item": "KT UNION-based SQL injection — leak table data", ...},
        {"test_item": "KT Stacked queries — DROP/DELETE bypass", ...},
        {"test_item": "KT Second-order SQL injection (stored payload)", ...},
        
        # NoSQL (2 case)
        {"test_item": "KT NoSQL injection — operator injection", ...},
        {"test_item": "KT NoSQL injection — JavaScript injection", ...},
        
        # Command (1 case gom các loại)
        {"test_item": "KT OS Command injection trên các text field", ...},
        
        # Header (2 case)
        {"test_item": "KT HTTP Header injection (CRLF injection)", ...},
        {"test_item": "KT Host Header injection", ...},
        
        # JSON parser (2 case)
        {"test_item": "KT JSON injection — Unicode escape", ...},
        {"test_item": "KT JSON parser confusion — duplicate keys", ...},
        
        # Auth bypass (3 case)
        {"test_item": "KT JWT algorithm confusion (alg: none)", ...},
        {"test_item": "KT JWT signature stripping", ...},
        {"test_item": "KT Mass assignment — gửi field không có trong spec", ...},
        
        # Content/Parser (2 case)
        {"test_item": "KT XXE injection — Content-Type: application/xml", ...},
        {"test_item": "KT SSRF qua field nhận URL", ...},
        
        # DoS (2 case)
        {"test_item": "KT Rate limit — flood request hợp lệ", ...},
        {"test_item": "KT Resource exhaustion — payload quá lớn", ...},
        
        # Replay (1 case)
        {"test_item": "KT Replay attack — gửi lại CÙNG request 2 lần", ...},
    ]
}
```

Tổng: **~20 TC** cho section Security Injection Deep.

---

## 14. Phối hợp với SecOps/Dev

Một số TC cần phối hợp với team khác để thực thi:

| Test | Cần phối hợp |
|---|---|
| Time-based blind SQL | Dev: timeout config < 5s; SecOps: log SIEM |
| Stacked queries (DROP) | Dev: snapshot/restore DB cho test environment |
| SSRF | Dev: simulate internal services nếu Sandbox không có |
| Mass assignment | Dev/BA: biết DB schema để verify field nào bị set |
| Rate limit flood | Dev/SRE: deploy rate limit config sandbox |
| XXE | Dev: confirm API có accept XML không |

---

## 15. Anti-pattern

| Anti-pattern | Sửa |
|---|---|
| Chỉ test 1 payload `' OR '1'='1` rồi tick "đã test SQL injection" | SQL injection có 6 kỹ thuật, mỗi loại cần test riêng |
| Test SQL injection mà không check log SIEM | SIEM log để monitor production attacks, phải verify |
| Bỏ qua second-order injection | Đây là bug phổ biến trong report/dashboard API |
| Không test Mass assignment | OWASP API3:2023 — bug phổ biến nhất |
| Test JWT chỉ với token expired | Phải cover: alg=none, signature stripping, algorithm substitution |
| Bỏ qua duplicate keys trong JSON | Bug bảo mật nghiêm trọng: validation và business logic xử lý khác key |
