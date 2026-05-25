# Non-Functional Testing — Performance, Security, Usability, Compatibility

Functional testing trả lời "**có hoạt động đúng không**". Non-functional trả lời "**hoạt động tốt đến đâu**". Bỏ qua nó là nguyên nhân chính của các sự cố production sau khi go-live.

---

## 1. Performance Testing

### 1.1 Phân loại

| Loại | Mục tiêu | Khi nào |
|---|---|---|
| **Load test** | Verify hệ thống chịu được expected load | Trước release |
| **Stress test** | Tìm breaking point | Định kỳ |
| **Spike test** | Phản ứng với lưu lượng tăng đột biến (flash sale, viral) | Trước event |
| **Soak / Endurance** | Vận hành ổn định trong thời gian dài (memory leak) | Trước release lớn |
| **Scalability** | Hệ thống scale theo expected growth | Trước event mở rộng |
| **Volume** | Xử lý data lớn (DB nhiều record) | Trước data migration |

### 1.2 Metrics bắt buộc đo

| Metric | Định nghĩa | Mục tiêu |
|---|---|---|
| **Response time** | Thời gian từ request đến nhận response đầy đủ | P95 < SLA |
| **Throughput** | Số request xử lý / giây | Đạt expected RPS |
| **Error rate** | % request fail trên total | < 1% under load |
| **Concurrent users** | Số user đồng thời đang dùng | Đạt expected |
| **CPU / Memory** | Server resource | < 70% sustained |
| **DB connection** | Pool usage | < 80% |
| **Network bandwidth** | In/out traffic | Trong limit |

**Lưu ý**: dùng **percentile (P50, P95, P99)** thay vì average. Average che giấu spike.

### 1.3 Cách lập performance test

1. **Định nghĩa SLA cụ thể**: vd "90% request `/api/orders` < 500ms với 1000 concurrent user".
2. **Build user journey thực tế** (không phải hammer 1 endpoint): user login → browse → add cart → checkout. Tỷ lệ phản ánh production analytics.
3. **Ramp-up gradient**: 0 → 1000 user trong 10 phút (không phải 1000 user ngay lập tức).
4. **Steady state**: giữ 1000 user trong 30 phút — đo P50/P95/P99.
5. **Ramp-down**: giảm dần để check cleanup.
6. **Test ở nhiều nơi**: gần server, xa server (CDN, multi-region).
7. **Monitor đồng thời**: app metrics, DB metrics, infra metrics — bottleneck nằm ở đâu.

### 1.4 Tool

| Tool | Mạnh ở |
|---|---|
| **k6** | Script JS, dễ CI/CD, cloud version | 
| **JMeter** | UI-based, plugin nhiều, mature |
| **Locust** | Python, distributed |
| **Gatling** | Scala, performance cao, report đẹp |
| **Artillery** | YAML, đơn giản, nhanh |
| **Lighthouse CI** | Web frontend perf |
| **WebPageTest** | Real device, real browser |

### 1.5 Bug performance điển hình

- N+1 query trong API list.
- Không có index → query tăng tuyến tính theo data size.
- Memory leak → restart định kỳ là workaround, không phải fix.
- Cache không hoạt động hoặc hit rate thấp.
- Synchronous khi nên async (gửi email block API).
- Không có connection pool / pool quá nhỏ.
- Bundle JS/CSS quá lớn → LCP tệ.
- Image không optimize, không lazy load.

---

## 2. Security Testing

### 2.1 Các tầng cần test

| Tầng | Trọng tâm |
|---|---|
| Network | TLS, certificate, firewall rule |
| App | OWASP Top 10 (Web), API Top 10 (API), Mobile Top 10 |
| Data | Encryption at rest & in transit |
| Auth | Session, token, MFA |
| Infra | Server hardening, secret management |

### 2.2 OWASP Top 10 (2021) — checklist nhanh

1. **Broken Access Control** → IDOR, privilege escalation, force browsing.
2. **Cryptographic Failures** → password lưu plaintext/MD5, HTTP, key trong code.
3. **Injection** → SQL, NoSQL, OS command, LDAP, XPath.
4. **Insecure Design** → thiếu rate limit, thiếu MFA cho admin.
5. **Security Misconfiguration** → default credential, debug mode, header thiếu.
6. **Vulnerable Components** → dependency có CVE.
7. **Identification and Authentication Failures** → brute force, session fixation.
8. **Software and Data Integrity Failures** → CI/CD bị compromise, package từ source không tin cậy.
9. **Security Logging and Monitoring Failures** → không log, log thiếu, không alert.
10. **Server-Side Request Forgery** → user truyền URL, server fetch.

### 2.3 Authentication & Session

- Password policy: min length, complexity, history (không reuse N password gần nhất).
- MFA: TOTP, SMS, biometric. Test bypass MFA.
- Account lockout sau N lần fail. Test lockout có ảnh hưởng user khác không (DOS).
- Forgot password: link một lần, expire 15–30 phút, không guess được.
- Session: idle timeout, absolute timeout, invalidate khi đổi password.
- Logout: clear session ở server, xóa token client.

### 2.4 Data Protection

- TLS 1.2+ enforce. Test bằng SSL Labs (qualys).
- Sensitive data: credit card masked (****1234), CCCD masked, email partially.
- PII trong log: bị scrub không.
- Backup: encrypted at rest.

### 2.5 Vulnerability scanning

- **SAST**: SonarQube, Semgrep, Snyk Code — quét source code.
- **DAST**: OWASP ZAP, Burp Suite — quét web app đang chạy.
- **Dependency scan**: Snyk, Dependabot, OWASP Dependency-Check.
- **Container scan**: Trivy, Grype.
- **Secret scan**: GitLeaks, TruffleHog — leak API key trong repo.

### 2.6 Penetration Testing

- Định kỳ 1 lần/năm hoặc trước release lớn — thuê bên thứ 3.
- QA hỗ trợ: cung cấp môi trường, account test, scope rõ ràng.
- Output: report findings → tracking như bug bình thường.

---

## 3. Usability Testing

### 3.1 Heuristic Evaluation (Nielsen's 10)

1. Visibility of system status — user biết hệ thống đang làm gì (loading, success).
2. Match between system and real world — dùng từ ngữ user hiểu, không jargon.
3. User control and freedom — undo, cancel, back.
4. Consistency and standards — UI pattern thống nhất.
5. Error prevention — confirm trước action destructive.
6. Recognition rather than recall — user không phải nhớ option ở màn trước.
7. Flexibility and efficiency — shortcut cho expert.
8. Aesthetic and minimalist design — không clutter.
9. Help users recognize, diagnose, recover from errors — error message rõ, có hướng giải quyết.
10. Help and documentation — có khi user cần.

### 3.2 User test

- 5 user là đủ để bắt 80% usability issue (Nielsen).
- **Task-based test**: cho user task cụ thể, quan sát họ làm.
- **Think-aloud**: user nói ra suy nghĩ.
- Đo: success rate, time on task, error count, satisfaction (SUS score).

### 3.3 A/B Test

QA hỗ trợ:
- Verify tracking event đúng cho từng variant.
- Verify random assignment đúng tỷ lệ.
- Verify metric calculation đúng (conversion rate, click-through).

---

## 4. Compatibility Testing

### 4.1 Browser/Device matrix
Xem `references/platform-web.md` mục 2.

### 4.2 OS version
- Web: server OS update không break app.
- Mobile: min OS supported đến latest OS.
- Desktop app: Windows 10/11, macOS 12/13/14.

### 4.3 Screen resolution
- Web: 1280×720 đến 2560×1440 + ultra-wide 3440×1440.
- Mobile: nhỏ nhất (iPhone SE 320pt) đến lớn nhất (Pro Max).

### 4.4 Network compatibility
- IPv4, IPv6.
- Behind corporate proxy, VPN.
- Restrictive firewall (chỉ port 80, 443).
- Slow connection (3G, satellite).

### 4.5 Backward compatibility
- API: client cũ vẫn hoạt động sau API update.
- Mobile app: user chưa update vẫn dùng được tới khi force update.
- Data migration: data từ version cũ load đúng version mới.

---

## 5. Reliability & Recovery

### 5.1 Disaster Recovery
- Backup được restore thành công không (test định kỳ).
- RTO (Recovery Time Objective): bao lâu để khôi phục.
- RPO (Recovery Point Objective): mất tối đa bao nhiêu data.
- Failover: primary down → secondary takeover trong bao lâu.
- Multi-region: traffic chuyển vùng đúng khi 1 region down.

### 5.2 Chaos Testing
- Kill 1 service → service khác có graceful degrade không.
- DB slow 5s → app có timeout đúng không.
- Network partition → app có behavior phù hợp không.
- Tool: Chaos Monkey, Litmus, Gremlin.

---

## 6. Localization (L10n) & Internationalization (i18n) — chi tiết

### Cho mỗi locale:
- Mọi string đã translate (không có English lẫn lộn).
- Không hardcode date format (`MM/DD/YYYY` của US vs `DD/MM/YYYY` của VN/EU vs `YYYY-MM-DD` của ISO).
- Number format: `1,234.56` (US) vs `1.234,56` (DE/VN tương đối) vs `1 234,56` (FR).
- Currency: ký hiệu, vị trí, decimal places (JPY không có decimal).
- Plural form: "1 item" / "2 items" — tiếng Nga, Ả-rập có nhiều form hơn.
- RTL languages (Ả-rập, Hebrew): layout đảo, icon đảo (back arrow), text alignment.
- Time zone: hiển thị theo user, lưu UTC.

---

## 7. Bảng quyết định: ưu tiên non-functional theo loại sản phẩm

| Loại sản phẩm | P0 (must) | P1 (should) | P2 (nice) |
|---|---|---|---|
| Banking / Fintech | Security, Reliability | Performance, A11y | i18n |
| E-commerce | Performance (peak), Security | Usability, A11y | i18n |
| Healthcare | Security (HIPAA), Reliability | Performance, A11y | — |
| Internal tool | Functionality | Usability, Compatibility | Performance |
| Public website | Performance, A11y, SEO | Compatibility, i18n | Security (basic) |
| Mobile game | Performance, Battery | Compatibility | i18n |
| Real-time (chat, trading) | Performance, Reliability | Security | A11y |

Không có dự án nào "không cần" non-functional — chỉ có khác về **mức độ ưu tiên**. Đừng để dev/PM nói "non-functional làm sau" mà bỏ qua hoàn toàn.

---

## 8. Checklist cuối cùng — pre-release non-functional

- [ ] Load test với expected peak — pass SLA
- [ ] Soak test 24h — không leak memory
- [ ] OWASP Top 10 — pass quick scan
- [ ] Dependency scan — không có Critical/High CVE
- [ ] Backup + restore test — pass
- [ ] Failover test — pass
- [ ] A11y audit (web) — WCAG AA
- [ ] Cross-browser/device matrix — pass
- [ ] Locale primary — translation hoàn thiện
- [ ] Monitoring + alert đã setup — kiểm chứng bằng giả lập sự cố
