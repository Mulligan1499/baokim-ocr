#!/usr/bin/env python3
"""
Reference script: convert Excel manual test case → CSV test data.

Usage:
    python convert_excel_to_csv.py <input.xlsx> <feature_name> [--sheet=<name>] [--out-dir=<dir>]

Output:
    - <feature>_data.csv      — single-field validation CSV
    - <feature>_warnings.md   — E2E TC bị skip (nếu có)

Đây là REFERENCE implementation — Claude nên adapt cho từng case, không phải copy nguyên si.
Quan trọng: đọc kỹ references/column-mapping-guide.md + sentinel-value-guide.md + e2e-detection-guide.md
trước khi customize.
"""
import argparse
import csv
import os
import re
import sys
from pathlib import Path

try:
    from openpyxl import load_workbook
except ImportError:
    print("Cần cài: pip install openpyxl --break-system-packages", file=sys.stderr)
    sys.exit(1)


# =============================================================
# Column header patterns — theo references/column-mapping-guide.md
# =============================================================
COLUMN_PATTERNS = {
    "case_id": [
        (r"^(tc[_ -]?id|test[_ -]?case[_ -]?id)$", 100),
        (r"^(case[_ -]?id|test[_ -]?id)$", 95),
        (r"^(mã test|mã tc|số tc|stt test)$", 85),
        (r"^id$", 75),
    ],
    "description": [
        (r"^(description|descriptions)$", 100),
        (r"^(test[_ -]?name|test[_ -]?title|test[_ -]?description)$", 95),
        (r"^(mô tả|tên test|tiêu đề)$", 90),
        (r"^(title|name|summary)$", 80),
    ],
    "test_steps": [
        (r"^(test[_ -]?steps?|steps)$", 100),
        (r"^(các bước|quy trình test|step thực hiện|cách thực hiện|bước thực hiện|thực hiện)$", 95),
        (r"^(procedure|action[_ -]?steps?)$", 85),
        (r"^(action|hành động)$", 80),
        (r"^(input|data|test[_ -]?data)$", 70),
    ],
    "expected_result": [
        (r"^expected[_ -]?(result|results)$", 100),
        (r"^(expected|outcome|expected[_ -]?outcome)$", 95),
        (r"^(kết quả mong đợi|kết quả expect|kqmd)$", 90),
        (r"^(result|kết quả)$", 80),
    ],
    "expected_code": [
        (r"^expected[_ -]?(status|code|status[_ -]?code|http[_ -]?code)$", 100),
        (r"^(http[_ -]?status|response[_ -]?code|status[_ -]?expect)$", 95),
        (r"^(status|http|code)$", 85),
    ],
    "expected_message": [
        (r"^expected[_ -]?(error|error[_ -]?code|message|error[_ -]?message)$", 100),
        (r"^(error[_ -]?code|error[_ -]?message|err[_ -]?code)$", 95),
        (r"^(mã lỗi|thông báo lỗi|message)$", 85),
    ],
    "tag": [
        (r"^tags?$", 100),
        (r"^(labels?|nhãn)$", 90),
        (r"^(priority|type|category|loại|loại test|mức ưu tiên|mức độ ưu tiên|độ ưu tiên|ưu tiên)$", 80),
        (r"^(severity|mức độ)$", 75),
    ],
}


# =============================================================
# Sentinel detection patterns
# =============================================================
EMPTY_PATTERNS = [
    r"để trống", r"\btrống\b", r"bỏ trống", r"\brỗng\b", r"để rỗng",
    r"không nhập", r"không điền",
    r"\bempty\b", r"empty string", r"\bblank\b",
    r"=\s*\"\"", r"=\s*''",
]

NULL_PATTERNS = [
    r"\bnull\b", r"giá trị null", r"set null", r"trả null",
    r"=\s*null\b",
]

MISSING_PATTERNS = [
    r"thiếu field", r"thiếu trường", r"không có field",
    r"không truyền", r"không gửi", r"không có trong payload",
    r"bỏ qua field",
    r"\bmissing\b", r"missing field", r"not provided",
    r"field not sent", r"omit field", r"without field",
    r"no \w+ in payload",
]


# =============================================================
# E2E detection — theo references/e2e-detection-guide.md
# =============================================================
E2E_KEYWORDS = [
    "quy trình", "luồng nghiệp vụ", "luồng", "chuỗi",
    "đầy đủ", "hoàn chỉnh", "tích hợp", "kết hợp",
    "kịch bản tổng",
    "end-to-end", "e2e", "end to end", "workflow",
    "full flow", "complete flow", "integration flow", "chained",
]

SEQUENCE_MARKERS = [
    r"\bb[uưừ]?[oơ]?[cức]?\s*\d+", r"\bstep\s*\d+", r"\bs\d+\s",
    r"\bb\d+\b", r"\d+\)\s",
    r"đầu tiên", r"sau đó", r"cuối cùng",
    r"\bfirst\b", r"\bthen\b", r"\bfinally\b",
]


def normalize_header(text):
    """Lowercase, strip, replace _/- → space."""
    if not text:
        return ""
    return re.sub(r"[_-]+", " ", str(text).strip().lower())


def match_column(header_text, column_key):
    """Match Excel header với patterns, return (matched, confidence)."""
    normalized = normalize_header(header_text)
    if not normalized:
        return False, 0
    for pattern, confidence in COLUMN_PATTERNS[column_key]:
        if re.search(pattern, normalized, re.IGNORECASE):
            return True, confidence
    return False, 0


def detect_column_mapping(header_row):
    """
    Trả về dict {csv_field: (excel_col_index, header_text, confidence)}
    Mapping nào không tìm được → không có trong dict.
    """
    mapping = {}
    for col_key in COLUMN_PATTERNS.keys():
        best_match = None
        for col_idx, cell_value in enumerate(header_row):
            matched, conf = match_column(cell_value, col_key)
            if matched and (best_match is None or conf > best_match[2]):
                best_match = (col_idx, str(cell_value).strip(), conf)
        if best_match:
            mapping[col_key] = best_match
    return mapping


def find_header_row(sheet, max_scan=5):
    """Scan top N rows tìm row có nhiều match nhất với column patterns."""
    best_row_idx = 1
    best_match_count = 0
    rows = list(sheet.iter_rows(min_row=1, max_row=max_scan, values_only=True))
    for idx, row in enumerate(rows, start=1):
        mapping = detect_column_mapping(row)
        if len(mapping) > best_match_count:
            best_match_count = len(mapping)
            best_row_idx = idx
    return best_row_idx


def detect_sentinel(text):
    """Detect sentinel value từ text. Return one of: <EMPTY>, <NULL>, <MISSING>, or None."""
    if not text:
        return None
    text_lower = text.lower()
    # Order matters: MISSING > NULL > EMPTY
    for pattern in MISSING_PATTERNS:
        if re.search(pattern, text_lower):
            return "<MISSING>"
    for pattern in NULL_PATTERNS:
        if re.search(pattern, text_lower):
            return "<NULL>"
    for pattern in EMPTY_PATTERNS:
        if re.search(pattern, text_lower):
            return "<EMPTY>"
    return None


def extract_endpoints(test_steps):
    """Extract list of API endpoints từ test steps."""
    pattern = r"(POST|GET|PUT|DELETE|PATCH)\s+(/[a-zA-Z0-9/\-_{}]+)"
    return re.findall(pattern, test_steps or "", re.IGNORECASE)


def is_e2e_flow(description, test_steps):
    """
    Detect E2E flow. Return (verdict, score, reasons).
    verdict: 'E2E', 'AMBIGUOUS', 'SINGLE_FIELD'
    """
    score = 0
    reasons = []
    text = ((description or "") + " " + (test_steps or "")).lower()

    # Signal A: E2E keywords
    for kw in E2E_KEYWORDS:
        if kw in text:
            score += 50
            reasons.append(f"Chứa từ khóa E2E: '{kw}'")
            break

    # Signal B: multiple endpoints
    endpoints = extract_endpoints(test_steps)
    unique_paths = set(ep[1] for ep in endpoints)
    if len(unique_paths) >= 2:
        score += 40
        reasons.append(f"Test Steps có {len(unique_paths)} endpoints khác nhau: {list(unique_paths)}")

    # Signal C: sequence markers
    marker_count = sum(1 for p in SEQUENCE_MARKERS if re.search(p, text, re.IGNORECASE))
    if marker_count >= 2 and len(unique_paths) >= 2:
        score += 30
        reasons.append(f"Có {marker_count} sequence markers + multi-endpoint")

    # Conflict: same endpoint repeated
    if endpoints and len(unique_paths) == 1 and len(endpoints) >= 2:
        score -= 30
        reasons.append("Repeated call trên cùng endpoint — có thể là idempotency test")

    # Decision
    if score >= 70:
        return "E2E", score, reasons
    elif score >= 40:
        return "AMBIGUOUS", score, reasons
    else:
        return "SINGLE_FIELD", score, reasons


def parse_expected_code(expected_result):
    """Extract HTTP status code từ Expected Result text."""
    if not expected_result:
        return None
    # Priority 1: explicit 3-digit code
    m = re.search(r"\b(2\d{2}|4\d{2}|5\d{2})\b", expected_result)
    if m:
        return m.group(1)
    # Priority 2: keyword mapping
    text_lower = expected_result.lower()
    keyword_map = [
        (["thành công", "success", "tạo thành công"], "200"),
        (["không tìm thấy", "not found"], "404"),
        (["unauthorized", "chưa login", "không có quyền"], "401"),
        (["forbidden", "không được phép", "bị từ chối"], "403"),
        (["invalid", "sai định dạng", "lỗi validation", "bad request"], "400"),
        (["conflict", "duplicate", "trùng", "xung đột"], "409"),
        (["internal error", "lỗi server"], "500"),
    ]
    for keywords, code in keyword_map:
        if any(k in text_lower for k in keywords):
            return code
    return None


KNOWN_STATUS_WORDS = {
    "PENDING", "AUTHORIZED", "CAPTURED", "REFUNDED", "VOIDED", "CANCELLED",
    "SETTLED", "FAILED", "EXPIRED", "ACTIVE", "INACTIVE", "DELETED",
    "OK", "OK_TRUE", "TRUE", "FALSE", "NULL", "EMPTY",
    "POST", "GET", "PUT", "DELETE", "PATCH",
    "USD", "VND", "EUR", "JPY",
}


def parse_expected_message(expected_result):
    """Extract error code (UPPER_SNAKE_CASE) từ Expected Result."""
    if not expected_result:
        return ""
    # Priority 1: explicit "error code: XXX" / "error: XXX" / "mã lỗi: XXX"
    m = re.search(r"(?:error[_ -]?code|error|mã lỗi)[:\s]+([A-Z][A-Z0-9_]{2,})", expected_result, re.IGNORECASE)
    if m and m.group(1).upper() not in KNOWN_STATUS_WORDS:
        return m.group(1)
    # Priority 2: any UPPER_SNAKE_CASE word that isn't a known status word
    for candidate in re.findall(r"\b([A-Z][A-Z0-9_]{3,})\b", expected_result):
        if candidate.upper() not in KNOWN_STATUS_WORDS:
            return candidate
    return ""


COMMON_FIELDS = [
    "customer.email", "customer.phone", "customer.name", "customer.address",
    "username", "password", "email", "phone", "user_id",
    "name", "full_name", "first_name", "last_name",
    "amount", "currency", "merchant_id", "order_id", "payment_id",
    "request_id", "idempotency_key", "description", "reason",
    "card_token", "card_number", "cvv", "expiry",
    "address", "city", "country", "zip_code", "postal_code",
    "title", "status", "type", "category",
]

# Phrases indicating a field is "context default" — KHÔNG phải target test
CONTEXT_PHRASES = [
    r"hợp lệ", r"đúng", r"\bvalid\b", r"correct", r"chính xác",
    r"default", r"mặc định", r"như cũ", r"bình thường",
]

# Phrases indicating a field IS target test
TARGET_PHRASES_BY_SENTINEL = {
    "<EMPTY>": [r"rỗng", r"trống", r"để trống", r"\bempty\b", r"\bblank\b"],
    "<NULL>": [r"\bnull\b", r"\bnil\b"],
    "<MISSING>": [r"thiếu", r"không truyền", r"không có", r"không gửi", r"\bmissing\b", r"\bomit\b", r"without"],
}

# Phrases for invalid/abnormal but with literal value
INVALID_LITERAL_PHRASES = [
    r"\bsai\b", r"\bwrong\b", r"\binvalid\b", r"không đúng",
    r"\bâm\b", r"\bnegative\b",
    r"quá ngắn", r"quá dài", r"too short", r"too long",
    r"vượt quá", r"\bexceed\b",
    r"sql injection", r"\bxss\b",
]


POSITIVE_INTENT_KEYWORDS = [
    "happy path", "hợp lệ", "thành công", "tạo thành công",
    "valid", "successful", "success case",
]

NEGATIVE_INTENT_KEYWORDS = [
    "lỗi", "sai", "rỗng", "thiếu", "âm",
    "invalid", "wrong", "missing", "null", "empty",
    "boundary", "biên", "vượt", "quá ngắn", "quá dài",
    "không tồn tại", "không support", "không hỗ trợ",
    "không hợp lệ", "không đúng", "không có",
    "duplicate", "trùng", "injection",
    "negative", "fail", "reject", "đã hết hạn", "expired",
    "bằng 0", "= 0", "zero",
]


def _split_into_segments(text):
    """Split test_steps thành các segment độc lập."""
    # Chỉ translate connector tiếng Việt (an toàn hơn — "or"/"and" có thể là SQL keyword hoặc giá trị)
    text = re.sub(r"\s+(và|hoặc)\s+", ", ", text, flags=re.IGNORECASE)
    # Split by comma/semicolon/newline — KHÔNG split bằng period (sẽ phá email/url)
    segments = re.split(r"[,;\n]+", text)
    return [s.strip() for s in segments if s.strip()]


def detect_tc_intent(description):
    """Detect intent: POSITIVE | NEGATIVE | UNKNOWN."""
    desc_lower = (description or "").lower()
    if any(k in desc_lower for k in POSITIVE_INTENT_KEYWORDS):
        return "POSITIVE"
    if any(k in desc_lower for k in NEGATIVE_INTENT_KEYWORDS):
        return "NEGATIVE"
    return "UNKNOWN"


def _find_hinted_fields_in_description(description):
    """Find fields explicitly mentioned trong Description.
    Allow space/underscore/hyphen variants: 'merchant_id' matches 'Merchant ID', 'merchant-id'.
    """
    desc_lower = (description or "").lower()
    hinted = []
    for field in COMMON_FIELDS:
        # Pattern variants: replace underscore with character class accepting _, space, hyphen
        # re.escape() doesn't escape underscore, so just replace directly
        field_pattern = re.escape(field).replace("_", "[_ -]")
        if re.search(r"\b" + field_pattern + r"\b", desc_lower):
            hinted.append(field)
    return hinted


def _classify_field_in_segment(segment, field):
    """
    Classify role of field within a single segment.
    Return: ('TARGET_SENTINEL', sentinel) | ('TARGET_LITERAL', value) | ('CONTEXT', None) | ('NOT_MENTIONED', None)
    """
    if not re.search(r"\b" + re.escape(field) + r"\b", segment, re.IGNORECASE):
        return ("NOT_MENTIONED", None)

    seg_lower = segment.lower()

    # CONTEXT (valid/default) — highest priority
    for phrase in CONTEXT_PHRASES:
        if re.search(phrase, seg_lower):
            return ("CONTEXT", None)

    # Sentinel keywords
    for sentinel, phrases in TARGET_PHRASES_BY_SENTINEL.items():
        for phrase in phrases:
            if re.search(phrase, seg_lower):
                return ("TARGET_SENTINEL", sentinel)

    # Invalid literal phrases — has invalid signal, extract literal
    for phrase in INVALID_LITERAL_PHRASES:
        if re.search(phrase, seg_lower):
            value = extract_literal_value(segment, field)
            return ("TARGET_LITERAL", value or "")

    # Has literal value but NO invalid signal → context (default valid) by default
    value = extract_literal_value(segment, field)
    if value is not None:
        return ("LITERAL_AMBIGUOUS", value)  # to be promoted/skipped based on intent

    return ("NOT_MENTIONED", None)


def extract_field_and_value(description, test_steps):
    """
    Suy field_name + test_value từ Description + Test Steps.
    Return list of (field_name, test_value).
    Empty list = happy path.

    Logic:
        - POSITIVE intent (Description nói "happy path", "hợp lệ") → return []
        - NEGATIVE intent → extract target field (sentinel/invalid signal OR hinted by description)
        - UNKNOWN intent → only return target có signal rõ ràng (sentinel/invalid)
    """
    if not test_steps and not description:
        return []

    intent = detect_tc_intent(description)
    if intent == "POSITIVE":
        return []  # happy path — không extract field

    hinted = _find_hinted_fields_in_description(description)

    segments = _split_into_segments(test_steps or "")
    target_fields = {}  # field → value

    for segment in segments:
        for field in COMMON_FIELDS:
            role, value = _classify_field_in_segment(segment, field)
            if role == "TARGET_SENTINEL":
                if field not in target_fields:
                    target_fields[field] = value
            elif role == "TARGET_LITERAL":
                if field not in target_fields:
                    target_fields[field] = value or ""
            elif role == "LITERAL_AMBIGUOUS":
                # Promote to target if:
                #   - Description has NEGATIVE intent AND field is hinted, OR
                #   - Description has hinted field (UNKNOWN intent + field hinted = likely target)
                if field in hinted and intent in ("NEGATIVE", "UNKNOWN"):
                    if field not in target_fields:
                        target_fields[field] = value or ""

    # Sentinel propagation: nếu Description nói "thiếu username và password" mà segment split
    # chỉ detect username, infer password cũng MISSING
    if intent == "NEGATIVE" and len(hinted) >= 2:
        # Tìm sentinel chung từ description
        desc_lower = (description or "").lower()
        propagated_sentinel = None
        for sentinel, phrases in TARGET_PHRASES_BY_SENTINEL.items():
            for phrase in phrases:
                if re.search(phrase, desc_lower):
                    propagated_sentinel = sentinel
                    break
            if propagated_sentinel:
                break
        if propagated_sentinel:
            for field in hinted:
                if field not in target_fields:
                    target_fields[field] = propagated_sentinel

    return list(target_fields.items())


def extract_literal_value(test_steps, field):
    """Try extract literal value cho 1 field từ Test Steps."""
    # Pattern: field = "value" hoặc field=value hoặc field: value
    patterns = [
        rf"{re.escape(field)}\s*[=:]\s*\"([^\"]*)\"",
        rf"{re.escape(field)}\s*[=:]\s*'([^']*)'",
        rf"{re.escape(field)}\s*[=:]\s*([^\s,\.]+)",
    ]
    for pattern in patterns:
        m = re.search(pattern, test_steps, re.IGNORECASE)
        if m:
            return m.group(1)
    return None


def detect_tags(description, raw_tag, test_steps=""):
    """Generate tags từ description + raw_tag column + test_steps."""
    tags = set()
    if raw_tag:
        # Parse existing tags (semicolon or comma)
        for sep in [";", ","]:
            if sep in raw_tag:
                tags.update(t.strip().lower() for t in raw_tag.split(sep) if t.strip())
                break
        else:
            tags.add(raw_tag.strip().lower())

    combined = ((description or "") + " " + (test_steps or "")).lower()
    if any(k in combined for k in ["happy path", "thành công", "hợp lệ", "valid"]):
        tags.add("positive")
    # Negative — bao gồm cả sentinel cases
    if any(k in combined for k in [
        "lỗi", "sai", "rỗng", "thiếu", "invalid", "negative", "wrong",
        "null", "missing", "không có", "không truyền", "trống", "âm",
    ]):
        tags.add("negative")
    if any(k in combined for k in ["boundary", "biên", "min", "max", "quá ngắn", "quá dài", "vượt"]):
        tags.add("boundary")
    if any(k in combined for k in ["sql injection", "xss", "security", "csrf"]):
        tags.update(["negative", "security", "critical"])
    if any(k in combined for k in ["smoke", "critical"]):
        tags.update(["smoke", "critical"])
    if any(k in combined for k in ["validation", "định dạng", "format"]):
        tags.add("validation")
    if "idempotent" in combined or "idempotency" in combined:
        tags.add("idempotency")

    # Normalize priority tags
    if "high" in tags or "cao" in tags:
        tags.discard("high")
        tags.discard("cao")
        tags.add("critical")
    if "medium" in tags or "trung bình" in tags:
        tags.discard("medium")
        tags.discard("trung bình")
    if "low" in tags or "thấp" in tags:
        tags.discard("low")
        tags.discard("thấp")

    # Resolve positive/negative conflict — nếu có negative thì bỏ positive
    if "negative" in tags and "positive" in tags:
        tags.discard("positive")

    if not tags:
        tags.add("regression")

    return ";".join(sorted(tags))


def convert_excel_to_csv(xlsx_path, feature_name, sheet_name=None, out_dir=None):
    """Main conversion function."""
    out_dir = Path(out_dir or ".")
    out_dir.mkdir(parents=True, exist_ok=True)

    wb = load_workbook(xlsx_path, data_only=True)

    # Pick sheet
    if sheet_name:
        if sheet_name not in wb.sheetnames:
            print(f"⚠️  Sheet '{sheet_name}' không tồn tại. Available: {wb.sheetnames}", file=sys.stderr)
            sys.exit(1)
        sheet = wb[sheet_name]
    else:
        # Auto-pick: sheet đầu tiên có header match
        best_sheet = None
        best_mapping_count = 0
        for ws_name in wb.sheetnames:
            ws = wb[ws_name]
            header_row_idx = find_header_row(ws)
            header = list(ws.iter_rows(min_row=header_row_idx, max_row=header_row_idx, values_only=True))[0]
            mapping = detect_column_mapping(header)
            if len(mapping) > best_mapping_count:
                best_mapping_count = len(mapping)
                best_sheet = ws_name
        if not best_sheet:
            print("⚠️  Không tìm thấy sheet nào match column pattern", file=sys.stderr)
            sys.exit(1)
        sheet = wb[best_sheet]
        print(f"📋 Auto-picked sheet: '{best_sheet}'")

    # Find header row
    header_row_idx = find_header_row(sheet)
    header = list(sheet.iter_rows(min_row=header_row_idx, max_row=header_row_idx, values_only=True))[0]
    mapping = detect_column_mapping(header)

    print(f"\n📋 Column mapping detected (header row {header_row_idx}):")
    for k, v in mapping.items():
        col_idx, col_text, conf = v
        print(f"  {k:20s} ← Cột {col_idx+1} '{col_text}' (confidence {conf}%)")

    # Required columns check
    required = ["case_id", "description"]
    missing_req = [r for r in required if r not in mapping]
    if missing_req:
        print(f"⚠️  Thiếu column bắt buộc: {missing_req}. Cần user xác nhận manual mapping.", file=sys.stderr)

    # Read data rows
    csv_rows = []
    warnings_md_blocks = []
    skipped_e2e = 0
    auto_id_counter = 1
    module_prefix = feature_name.upper()

    for row_idx, row in enumerate(sheet.iter_rows(min_row=header_row_idx + 1, values_only=True), start=header_row_idx + 1):
        if not row or all(c is None for c in row):
            continue

        # Extract values
        def get(field):
            if field not in mapping:
                return ""
            col_idx = mapping[field][0]
            val = row[col_idx] if col_idx < len(row) else None
            return str(val).strip() if val is not None else ""

        case_id = get("case_id") or f"TC_{module_prefix}_{auto_id_counter:03d}"
        auto_id_counter += 1
        description = get("description")
        test_steps = get("test_steps")
        expected_result = get("expected_result")
        raw_tag = get("tag")

        # E2E detection
        verdict, score, reasons = is_e2e_flow(description, test_steps)
        if verdict == "E2E":
            skipped_e2e += 1
            warnings_md_blocks.append(format_e2e_warning(case_id, description, test_steps, expected_result, reasons))
            continue

        if verdict == "AMBIGUOUS":
            # Còn keep CSV row nhưng print warning
            print(f"  ⚠️  {case_id}: AMBIGUOUS (score={score}) — reasons: {reasons[:2]}")

        # Expected code + message
        if "expected_code" in mapping:
            expected_code = get("expected_code")
        else:
            expected_code = parse_expected_code(expected_result) or ""

        if "expected_message" in mapping:
            expected_message = get("expected_message")
        else:
            expected_message = parse_expected_message(expected_result)

        # Field + value extraction
        field_values = extract_field_and_value(description, test_steps)

        # Tags
        tags = detect_tags(description, raw_tag, test_steps)

        # Happy path: không có field mentioned, expected_code 2xx
        if not field_values:
            # Single row, field_name + test_value rỗng
            csv_rows.append([case_id, description, "", "", expected_code, expected_message, tags])
        elif len(field_values) == 1:
            field, value = field_values[0]
            csv_rows.append([case_id, description, field, value, expected_code, expected_message, tags])
        else:
            # Multi-field → split
            for idx, (field, value) in enumerate(field_values):
                suffix = chr(ord('a') + idx)
                split_id = f"{case_id}{suffix}"
                split_desc = f"{description} (tách từ {case_id}, field={field})"
                csv_rows.append([split_id, split_desc, field, value, expected_code, expected_message, tags])

    # Write CSV
    csv_path = out_dir / f"{feature_name}_data.csv"
    with open(csv_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["Case_id", "Descriptions", "field_name", "test_value", "expected_code", "expected_message", "Tag"])
        writer.writerows(csv_rows)
    print(f"\n✅ CSV output: {csv_path} ({len(csv_rows)} rows)")

    # Write warnings.md nếu có E2E TC
    if warnings_md_blocks:
        warnings_path = out_dir / f"{feature_name}_warnings.md"
        with open(warnings_path, "w", encoding="utf-8") as f:
            f.write(f"# E2E Flow Test Cases — {feature_name}\n\n")
            f.write("Skill đã skip các test case sau vì là E2E flow.\n")
            f.write("Anh cần viết tay trong file `.robot` theo pattern flow keyword.\n\n")
            f.write("Xem skill `robot-script-writer` để gen E2E test suite.\n\n---\n\n")
            f.write("\n\n---\n\n".join(warnings_md_blocks))
        print(f"⚠️  Warnings: {warnings_path} ({skipped_e2e} E2E TC skipped)")

    # Summary
    print(f"\n📊 Conversion summary:")
    print(f"  Total Excel rows processed: {len(csv_rows) + skipped_e2e}")
    print(f"  CSV rows generated: {len(csv_rows)}")
    print(f"  E2E TC skipped: {skipped_e2e}")


def format_e2e_warning(case_id, description, test_steps, expected_result, reasons):
    """Format 1 E2E TC vào markdown block."""
    return f"""## {case_id} — {description}

**Description:** {description}

**Test Steps (từ Excel):**
```
{test_steps}
```

**Expected Result:** {expected_result}

**Lý do skip:**
{chr(10).join('- ' + r for r in reasons)}

**Đề xuất:** Viết flow keyword trong `<domain>_flows.robot`, sau đó tạo test case trong `tests/<domain>/<feature>_e2e.robot`."""


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Convert Excel manual TC → CSV test data")
    parser.add_argument("xlsx_path", help="Path to input Excel file")
    parser.add_argument("feature_name", help="Feature name (vd: login, register, create_order)")
    parser.add_argument("--sheet", help="Specific sheet name (default: auto-detect)")
    parser.add_argument("--out-dir", help="Output directory (default: current)")
    args = parser.parse_args()

    if not os.path.isfile(args.xlsx_path):
        print(f"⚠️  File không tồn tại: {args.xlsx_path}", file=sys.stderr)
        sys.exit(1)

    convert_excel_to_csv(args.xlsx_path, args.feature_name, args.sheet, args.out_dir)
