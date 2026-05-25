"""
Generate file Excel test case theo template chuẩn của project.

Template format:
- Sheet name = tên Module
- Row 1: trống (height=20)
- Row 2: Module Code | <code> | ... | Round 1 (G2:K2) | Round 2 (L2:P2) | Round 3 (Q2:U2)
- Row 3: Total Created | <count> | % Executed | <%> | ... | Pass | Fail | Impact | Not Run | Total (×3)
- Row 4: Total Plan to Executed | <count> | Creator | <name> | ... | counts (×3)
- Row 5: trống
- Row 6: HEADER chính: ID | Test Item | Pre-Condition | Step | Expected Output | Priority | Round 1..3
- Row 7: HEADER phụ Round: Result | Bug ID | Tester | Test Date | Note (×3)
- Row 8+: test cases. Có thể chèn "section header" (row xanh nhạt, bold, A col only)

Cách dùng:
    from generate_testcase_xlsx import create_testcase_workbook

    sections = [
        {
            "name": "Đăng nhập",
            "test_cases": [
                {
                    "test_item": "Login với email + password đúng",
                    "pre_condition": "User active đã có account",
                    "step": "1. Nhập email\n2. Nhập password\n3. Click Login",
                    "expected": "Vào dashboard, hiển thị tên user",
                    "priority": "High",
                },
                ...
            ]
        }
    ]

    create_testcase_workbook(
        output_path="output.xlsx",
        module_name="Đăng nhập",       # tên sheet
        module_code="LOGIN",            # prefix cho ID auto
        creator="QA Team",
        sections=sections,
    )
"""

from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter
from typing import List, Dict


# Màu sắc theo template gốc
COLOR_MODULE_HEADER = "B4C6E7"      # xanh nhạt cho ô header module info
COLOR_ROUND1 = "5BC95E"              # xanh lá — Round 1
COLOR_ROUND2 = "F7CAAC"              # cam — Round 2
COLOR_ROUND3 = "D8D8D8"              # xám — Round 3
COLOR_PASS = "C2D69B"                # xanh nhạt — Pass
COLOR_FAIL = "FBD4B4"                # cam nhạt — Fail
COLOR_IMPACT = "B2A1C7"              # tím nhạt — Impact
COLOR_NOTRUN = "BFBFBF"              # xám nhạt — Not Run
COLOR_TOTAL = "B8CCE4"               # xanh dương nhạt — Total
COLOR_MAIN_HEADER = "FFCC66"         # cam vàng — header Test Item, Step...
COLOR_SECTION = "CFE2F3"             # xanh rất nhạt — section header
COLOR_PRIORITY_HIGH = "F4CCCC"       # đỏ nhạt
COLOR_PRIORITY_MEDIUM = "FFF2CC"     # vàng nhạt
COLOR_PRIORITY_LOW = "D9EAD3"        # xanh nhạt

THIN_BORDER = Border(
    left=Side(style="thin", color="999999"),
    right=Side(style="thin", color="999999"),
    top=Side(style="thin", color="999999"),
    bottom=Side(style="thin", color="999999"),
)

FONT_DEFAULT = Font(name="Arial", size=10)
FONT_BOLD = Font(name="Arial", size=10, bold=True)
FONT_HEADER = Font(name="Arial", size=11, bold=True)


def _fill(color_hex: str) -> PatternFill:
    return PatternFill("solid", start_color=color_hex, end_color=color_hex)


def _set_header_block(ws, module_code: str, creator: str = ""):
    """Set rows 1-7 (header block) theo template chuẩn."""
    ws.row_dimensions[1].height = 20
    ws.row_dimensions[2].height = 22
    ws.row_dimensions[3].height = 21
    ws.row_dimensions[4].height = 34
    ws.row_dimensions[5].height = 16
    ws.row_dimensions[6].height = 22
    ws.row_dimensions[7].height = 22

    # Row 2 — module info + round labels
    ws["A2"] = "Module Code"
    ws["A2"].fill = _fill(COLOR_MODULE_HEADER)
    ws["A2"].font = FONT_BOLD
    ws["A2"].alignment = Alignment(horizontal="center", vertical="center")
    ws["A2"].border = THIN_BORDER

    ws["B2"] = module_code
    ws["B2"].font = FONT_BOLD
    ws["B2"].alignment = Alignment(horizontal="center", vertical="center")
    ws["B2"].border = THIN_BORDER

    ws.merge_cells("G2:K2")
    ws["G2"] = "Round 1"
    ws["G2"].fill = _fill(COLOR_ROUND1)
    ws["G2"].font = FONT_BOLD
    ws["G2"].alignment = Alignment(horizontal="center", vertical="center")

    ws.merge_cells("L2:P2")
    ws["L2"] = "Round 2"
    ws["L2"].fill = _fill(COLOR_ROUND2)
    ws["L2"].font = FONT_BOLD
    ws["L2"].alignment = Alignment(horizontal="center", vertical="center")

    ws.merge_cells("Q2:U2")
    ws["Q2"] = "Round 3"
    ws["Q2"].fill = _fill(COLOR_ROUND3)
    ws["Q2"].font = FONT_BOLD
    ws["Q2"].alignment = Alignment(horizontal="center", vertical="center")

    # Row 3 — Pass/Fail/Impact/Not Run/Total (×3)
    ws["A3"] = "Total of Test cases Created"
    ws["A3"].fill = _fill(COLOR_MODULE_HEADER)
    ws["A3"].font = FONT_BOLD
    ws["A3"].alignment = Alignment(horizontal="left", vertical="center", wrap_text=True)
    ws["A3"].border = THIN_BORDER

    ws["B3"] = '=COUNTIF(E8:E1865,"<>")'
    ws["B3"].font = FONT_BOLD
    ws["B3"].alignment = Alignment(horizontal="center", vertical="center")
    ws["B3"].border = THIN_BORDER

    ws["C3"] = "% Executed round 1"
    ws["C3"].fill = _fill(COLOR_MODULE_HEADER)
    ws["C3"].font = FONT_BOLD
    ws["C3"].alignment = Alignment(horizontal="center", vertical="center")
    ws["C3"].border = THIN_BORDER

    ws["D3"] = "=IFERROR(B3/B4,0)"
    ws["D3"].number_format = "0.00%"
    ws["D3"].font = FONT_BOLD
    ws["D3"].alignment = Alignment(horizontal="center", vertical="center")
    ws["D3"].border = THIN_BORDER

    # Sub-headers cho từng round
    sub_headers = ["Pass", "Fail", "Impact", "Not Run", "Total"]
    sub_fills = [COLOR_PASS, COLOR_FAIL, COLOR_IMPACT, COLOR_NOTRUN, COLOR_TOTAL]
    for round_idx, start_col in enumerate([7, 12, 17]):  # G, L, Q
        for i, (label, color) in enumerate(zip(sub_headers, sub_fills)):
            cell = ws.cell(row=3, column=start_col + i, value=label)
            cell.fill = _fill(color)
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.border = THIN_BORDER
            cell.font = FONT_DEFAULT

    # Row 4 — counts via COUNTIFS
    ws["A4"] = "Total of Test cases plan to Executed"
    ws["A4"].fill = _fill(COLOR_MODULE_HEADER)
    ws["A4"].font = FONT_BOLD
    ws["A4"].alignment = Alignment(horizontal="left", vertical="center", wrap_text=True)
    ws["A4"].border = THIN_BORDER

    ws["B4"] = "=K4+P4"
    ws["B4"].font = FONT_BOLD
    ws["B4"].alignment = Alignment(horizontal="center", vertical="center")
    ws["B4"].border = THIN_BORDER

    ws["C4"] = "Creator"
    ws["C4"].fill = _fill(COLOR_MODULE_HEADER)
    ws["C4"].font = FONT_BOLD
    ws["C4"].alignment = Alignment(horizontal="center", vertical="center")
    ws["C4"].border = THIN_BORDER

    ws["D4"] = creator
    ws["D4"].font = FONT_BOLD
    ws["D4"].alignment = Alignment(horizontal="center", vertical="center")
    ws["D4"].border = THIN_BORDER

    # Counts cho mỗi round
    for round_letter in ["G", "L", "Q"]:
        next_idx = ord(round_letter) - ord("A") + 1  # G=7, L=12, Q=17
        col_pass = get_column_letter(next_idx)
        col_fail = get_column_letter(next_idx + 1)
        col_impact = get_column_letter(next_idx + 2)
        col_notrun = get_column_letter(next_idx + 3)
        col_total = get_column_letter(next_idx + 4)

        ws[f"{col_pass}4"] = f'=COUNTIFS(${col_pass}$6:${col_pass}$1406,"Pass")'
        ws[f"{col_fail}4"] = f'=COUNTIFS(${col_pass}$6:${col_pass}$1406,"Fail")'
        ws[f"{col_impact}4"] = f'=COUNTIFS(${col_pass}$6:${col_pass}$1406,"Impact")'
        ws[f"{col_notrun}4"] = f'=COUNTIFS(${col_pass}$6:${col_pass}$1406,"Not Run")'
        ws[f"{col_total}4"] = f"=SUM({col_pass}4:{col_notrun}4)"

        for col_letter in [col_pass, col_fail, col_impact, col_notrun, col_total]:
            cell = ws[f"{col_letter}4"]
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.border = THIN_BORDER
            cell.font = FONT_DEFAULT

    # Row 6 — main header
    main_headers = [
        ("A", "ID"),
        ("B", "Test Item"),
        ("C", "Pre - Condition"),
        ("D", "Step"),
        ("E", "Expected Output"),
        ("F", "Priority"),
    ]
    for col, text in main_headers:
        ws.merge_cells(f"{col}6:{col}7")
        cell = ws[f"{col}6"]
        cell.value = text
        cell.fill = _fill(COLOR_MAIN_HEADER)
        cell.font = FONT_HEADER
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        cell.border = THIN_BORDER

    ws.merge_cells("G6:K6")
    ws["G6"] = "Round 1"
    ws["G6"].fill = _fill(COLOR_ROUND1)
    ws["G6"].font = FONT_HEADER
    ws["G6"].alignment = Alignment(horizontal="center", vertical="center")

    ws.merge_cells("L6:P6")
    ws["L6"] = "Round 2"
    ws["L6"].fill = _fill(COLOR_ROUND2)
    ws["L6"].font = FONT_HEADER
    ws["L6"].alignment = Alignment(horizontal="center", vertical="center")

    ws.merge_cells("Q6:U6")
    ws["Q6"] = "Round 3"
    ws["Q6"].fill = _fill(COLOR_ROUND3)
    ws["Q6"].font = FONT_HEADER
    ws["Q6"].alignment = Alignment(horizontal="center", vertical="center")

    # Row 7 — sub-headers Round
    round_sub = ["Result", "Bug ID", "Tester", "Test Date", "Note"]
    for round_idx, (start_col, color) in enumerate(
        [(7, COLOR_ROUND1), (12, COLOR_ROUND2), (17, COLOR_ROUND3)]
    ):
        for i, label in enumerate(round_sub):
            cell = ws.cell(row=7, column=start_col + i, value=label)
            cell.fill = _fill(color)
            cell.font = FONT_BOLD
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.border = THIN_BORDER


def _set_column_widths(ws):
    widths = {
        "A": 14,   # ID
        "B": 35,   # Test Item
        "C": 30,   # Pre - Condition
        "D": 50,   # Step (rộng nhất)
        "E": 40,   # Expected Output
        "F": 10,   # Priority
        "G": 10, "H": 12, "I": 12, "J": 13, "K": 25,  # Round 1
        "L": 10, "M": 12, "N": 12, "O": 13, "P": 25,  # Round 2
        "Q": 10, "R": 12, "S": 12, "T": 13, "U": 25,  # Round 3
    }
    for col_letter, width in widths.items():
        ws.column_dimensions[col_letter].width = width


def _priority_fill(priority: str) -> PatternFill:
    p = (priority or "").strip().lower()
    if p in ("high", "p1", "critical", "cao"):
        return _fill(COLOR_PRIORITY_HIGH)
    if p in ("medium", "p2", "trung bình", "tb"):
        return _fill(COLOR_PRIORITY_MEDIUM)
    if p in ("low", "p3", "p4", "thấp", "thap"):
        return _fill(COLOR_PRIORITY_LOW)
    return PatternFill()  # no fill


def _add_section_header(ws, row: int, name: str):
    """Tô màu xanh nhạt toàn row làm section header, value ở cột A."""
    for col_idx in range(1, 22):  # A..U
        cell = ws.cell(row=row, column=col_idx)
        cell.fill = _fill(COLOR_SECTION)
        cell.font = FONT_BOLD
        if col_idx == 1:
            cell.value = name
        cell.border = THIN_BORDER
        cell.alignment = Alignment(horizontal="left", vertical="center")
    ws.row_dimensions[row].height = 22


def _add_test_case_row(ws, row: int, tc: Dict):
    """Thêm 1 test case row vào sheet."""
    # Cột A — formula tự sinh ID dạng [ModuleCode-N]
    # Logic: ROW()-7 cho TC đầu = 2, trừ COUNTBLANK ($E$8:E<row>) để bỏ qua section header
    # Section header có E trống → COUNTBLANK đếm vào, được trừ ra → đúng số TC.
    a_formula = (
        f'=IF(OR(D{row}<>"",E{row}<>""),'
        f'"["&$B$2&"-"&TEXT(ROW()-7-COUNTBLANK($E$8:E{row}),"##")&"]","")'
    )
    ws.cell(row=row, column=1, value=a_formula)
    ws.cell(row=row, column=2, value=tc.get("test_item", ""))
    ws.cell(row=row, column=3, value=tc.get("pre_condition", ""))
    ws.cell(row=row, column=4, value=tc.get("step", ""))
    ws.cell(row=row, column=5, value=tc.get("expected", ""))
    ws.cell(row=row, column=6, value=tc.get("priority", "Medium"))

    # Format từng cell
    for col_idx in range(1, 22):
        cell = ws.cell(row=row, column=col_idx)
        cell.border = THIN_BORDER
        cell.font = FONT_DEFAULT
        if col_idx == 1:  # ID — center
            cell.alignment = Alignment(horizontal="center", vertical="top", wrap_text=True)
        elif col_idx == 6:  # Priority — center, có color
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.fill = _priority_fill(tc.get("priority", ""))
        else:
            cell.alignment = Alignment(horizontal="left", vertical="top", wrap_text=True)

    # Auto height: ước lượng theo content dài nhất
    max_lines = 1
    for field in ["test_item", "pre_condition", "step", "expected"]:
        text = tc.get(field, "") or ""
        # Đếm \n + ước lượng wrap (mỗi 50 ký tự = 1 line cho cột rộng)
        lines = text.count("\n") + max(1, len(text) // 50)
        max_lines = max(max_lines, lines)
    ws.row_dimensions[row].height = max(22, min(max_lines * 16, 200))


def create_testcase_workbook(
    output_path: str,
    module_name: str,
    module_code: str,
    sections: List[Dict],
    creator: str = "",
):
    """
    Tạo file Excel test case theo template chuẩn — 1 SHEET.

    Args:
        output_path: đường dẫn file output (.xlsx)
        module_name: tên module — sẽ là tên sheet (vd "Đăng nhập")
        module_code: mã module — prefix cho ID auto-sinh (vd "LOGIN")
        sections: list các section, mỗi section có "name" và "test_cases"
        creator: tên QA tạo file
    """
    wb = Workbook()
    ws = wb.active
    ws.title = module_name[:31]  # Excel sheet name limit 31 chars

    _set_header_block(ws, module_code=module_code, creator=creator)
    _set_column_widths(ws)

    current_row = 8
    for section in sections:
        _add_section_header(ws, current_row, section["name"])
        current_row += 1
        for tc in section.get("test_cases", []):
            _add_test_case_row(ws, current_row, tc)
            current_row += 1

    # Freeze header
    ws.freeze_panes = "A8"

    wb.save(output_path)
    return output_path


def create_multi_module_workbook(
    output_path: str,
    modules: List[Dict],
    creator: str = "",
):
    """
    Tạo file Excel với NHIỀU SHEET — mỗi module 1 sheet.
    Hữu ích khi 1 feature có nhiều layer (UI + API + DB + E2E)
    hoặc 1 release có nhiều module test cùng lúc.

    Args:
        output_path: đường dẫn file output (.xlsx)
        modules: list of dict, mỗi module có:
            {
                "name": "Tên module" (= sheet name),
                "code": "MODULE_CODE" (prefix ID),
                "sections": [...] (giống create_testcase_workbook)
            }
        creator: tên QA tạo file (áp dụng cho tất cả sheet)
    """
    wb = Workbook()
    # Xóa sheet mặc định
    wb.remove(wb.active)

    for module in modules:
        ws = wb.create_sheet(title=module["name"][:31])

        _set_header_block(ws, module_code=module["code"], creator=creator)
        _set_column_widths(ws)

        current_row = 8
        for section in module.get("sections", []):
            _add_section_header(ws, current_row, section["name"])
            current_row += 1
            for tc in section.get("test_cases", []):
                _add_test_case_row(ws, current_row, tc)
                current_row += 1

        ws.freeze_panes = "A8"

    wb.save(output_path)
    return output_path


# ===== CLI for quick test =====
if __name__ == "__main__":
    sample_sections = [
        {
            "name": "Đăng nhập — Happy Path",
            "test_cases": [
                {
                    "test_item": "Login với email + password đúng",
                    "pre_condition": "User active đã có account\nĐang ở /login\nBrowser Chrome 120",
                    "step": "1. Nhập email = qa@example.com\n2. Nhập password = Pass@1234\n3. Click button Login",
                    "expected": "URL chuyển sang /dashboard trong 2s\nHeader hiển thị tên user\nCookie session_id được set với HttpOnly\nLog DB login_history có record mới",
                    "priority": "High",
                },
                {
                    "test_item": "Login với email viết hoa",
                    "pre_condition": "User active",
                    "step": "1. Nhập email = QA@EXAMPLE.COM\n2. Nhập password đúng\n3. Click Login",
                    "expected": "Login thành công (email case-insensitive)",
                    "priority": "Medium",
                },
            ],
        },
        {
            "name": "Đăng nhập — Negative",
            "test_cases": [
                {
                    "test_item": "Login với password sai",
                    "pre_condition": "User active",
                    "step": "1. Nhập email đúng\n2. Nhập password sai\n3. Click Login",
                    "expected": "Hiển thị error: 'Email hoặc mật khẩu không đúng'\nKhông lộ là email tồn tại hay không\nKhông redirect",
                    "priority": "High",
                },
                {
                    "test_item": "Login với email không tồn tại",
                    "pre_condition": "Email chưa đăng ký",
                    "step": "1. Nhập email không tồn tại\n2. Nhập password bất kỳ\n3. Click Login",
                    "expected": "Error message giống trường hợp password sai (security best practice)",
                    "priority": "High",
                },
                {
                    "test_item": "Khóa account sau 5 lần fail",
                    "pre_condition": "User active",
                    "step": "1. Login fail 5 lần liên tiếp\n2. Lần 6 login với password đúng",
                    "expected": "Account lock 15 phút\nMessage: 'Account tạm khóa do nhiều lần đăng nhập sai'\nEmail thông báo gửi đến user",
                    "priority": "High",
                },
            ],
        },
    ]

    create_testcase_workbook(
        output_path="/tmp/test_login.xlsx",
        module_name="Đăng nhập",
        module_code="LOGIN",
        creator="QA Team",
        sections=sample_sections,
    )
    print("Created /tmp/test_login.xlsx")
