# E2E Flow Test Cases — create_order

Skill đã skip các test case sau vì là E2E flow.
Anh cần viết tay trong file `.robot` theo pattern flow keyword.

Xem skill `robot-script-writer` để gen E2E test suite.

---

## TC_E2E_001 — Quy trình thanh toán đầy đủ

**Description:** Quy trình thanh toán đầy đủ

**Test Steps (từ Excel):**
```
B1: POST /auth/login. B2: POST /orders với amount=100000. B3: POST /payments với order_id từ B2. B4: POST /payments/{id}/capture
```

**Expected Result:** Order chuyển PENDING → AUTHORIZED → CAPTURED

**Lý do skip:**
- Chứa từ khóa E2E: 'quy trình'
- Test Steps có 4 endpoints khác nhau: ['/payments', '/auth/login', '/payments/{id}/capture', '/orders']
- Có 2 sequence markers + multi-endpoint

**Đề xuất:** Viết flow keyword trong `<domain>_flows.robot`, sau đó tạo test case trong `tests/<domain>/<feature>_e2e.robot`.