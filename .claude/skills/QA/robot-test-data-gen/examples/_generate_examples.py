#!/usr/bin/env python3
"""Generate example Excel input file for testing skill."""
from openpyxl import Workbook
from pathlib import Path

OUT_DIR = Path(__file__).parent

def make_login_example():
    wb = Workbook()
    ws = wb.active
    ws.title = "Login Test Cases"

    # Header row
    ws.append(["TC_ID", "Description", "Test Steps", "Expected Result", "Priority"])

    # Data rows — mix of single-field, multi-field, happy path, E2E
    rows = [
        ("TC_LOGIN_001", "Login hợp lệ", "POST /auth/login với username='qa_user@example.com' và password='Valid@Pass123'", "200 OK, trả về access_token và refresh_token", "High"),
        ("TC_LOGIN_002", "Username rỗng", "POST /auth/login để username trống, password hợp lệ", "400 Bad Request, error code USERNAME_REQUIRED", "High"),
        ("TC_LOGIN_003", "Username thiếu trường", "POST /auth/login không có field username trong payload", "400 Bad Request, USERNAME_REQUIRED", "High"),
        ("TC_LOGIN_004", "Password sai", "POST /auth/login với username hợp lệ, password='WrongPassword'", "401 Unauthorized, error code INVALID_CREDENTIALS", "Critical"),
        ("TC_LOGIN_005", "Thiếu username và password", "POST /auth/login không truyền cả username và password", "400 Bad Request, USERNAME_REQUIRED", "Medium"),
        ("TC_LOGIN_006", "SQL injection trong username", "POST /auth/login với username=\"' OR '1'='1\"", "401, INVALID_CREDENTIALS", "Security"),
        ("TC_LOGIN_007", "Username = null", "POST /auth/login set username = null", "400 USERNAME_REQUIRED", "Medium"),
        ("TC_LOGIN_008", "Quy trình login và refresh token", "B1: POST /auth/login để lấy refresh_token. B2: POST /auth/refresh với refresh_token. B3: GET /users/me với access_token mới", "Lấy được access_token mới từ refresh_token", "High"),
    ]
    for row in rows:
        ws.append(row)

    out_path = OUT_DIR / "login_manual_tc.xlsx"
    wb.save(out_path)
    print(f"Created: {out_path}")


def make_order_example():
    wb = Workbook()
    ws = wb.active
    ws.title = "Create Order Test Cases"

    ws.append(["Case ID", "Mô tả", "Các bước", "Kết quả mong đợi", "Loại test"])

    rows = [
        ("TC_ORD_001", "Tạo order hợp lệ", "POST /orders với merchant_id='TEST-MCH-001', amount=100000, currency='VND'", "201 Created, trả về order_id và status=PENDING", "happy path"),
        ("TC_ORD_002", "Amount bằng 0", "POST /orders với amount = 0", "400 Bad Request, AMT_TOO_SMALL", "boundary"),
        ("TC_ORD_003", "Amount âm", "POST /orders với amount = -100", "400 Bad Request, AMT_NEGATIVE", "negative"),
        ("TC_ORD_004", "Currency không support", "POST /orders với currency = 'XYZ'", "400 CURRENCY_NOT_SUPPORTED", "validation"),
        ("TC_ORD_005", "Merchant ID không tồn tại", "POST /orders với merchant_id = 'NONEXIST-999'", "404 MERCHANT_NOT_FOUND", "negative"),
        ("TC_E2E_001", "Quy trình thanh toán đầy đủ", "B1: POST /auth/login. B2: POST /orders với amount=100000. B3: POST /payments với order_id từ B2. B4: POST /payments/{id}/capture", "Order chuyển PENDING → AUTHORIZED → CAPTURED", "e2e"),
    ]
    for row in rows:
        ws.append(row)

    out_path = OUT_DIR / "create_order_manual_tc.xlsx"
    wb.save(out_path)
    print(f"Created: {out_path}")


if __name__ == "__main__":
    make_login_example()
    make_order_example()
