# E2E Flow Test Cases — login

Skill đã skip các test case sau vì là E2E flow.
Anh cần viết tay trong file `.robot` theo pattern flow keyword.

Xem skill `robot-script-writer` để gen E2E test suite.

---

## TC_LOGIN_008 — Quy trình login và refresh token

**Description:** Quy trình login và refresh token

**Test Steps (từ Excel):**
```
B1: POST /auth/login để lấy refresh_token. B2: POST /auth/refresh với refresh_token. B3: GET /users/me với access_token mới
```

**Expected Result:** Lấy được access_token mới từ refresh_token

**Lý do skip:**
- Chứa từ khóa E2E: 'quy trình'
- Test Steps có 3 endpoints khác nhau: ['/auth/login', '/users/me', '/auth/refresh']
- Có 2 sequence markers + multi-endpoint

**Đề xuất:** Viết flow keyword trong `<domain>_flows.robot`, sau đó tạo test case trong `tests/<domain>/<feature>_e2e.robot`.