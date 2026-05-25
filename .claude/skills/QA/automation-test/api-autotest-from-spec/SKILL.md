---
name: api-autotest-from-spec
description: |
  [WHAT] Build 1 bộ Robot Framework autotest hoàn chỉnh cho REST API từ 2 nguồn: (1) tài liệu API spec (PDF/MD/Postman/OpenAPI) và (2) bảng test case manual (xlsx/csv). Tạo schema validator, business-rule validator (formula compute & compare), CSV data-driven cho field validation, individual Robot cho flow phức tạp, kèm helper tái sử dụng được.
  [WHEN] User nói: "build autotest cho API X từ tài liệu", "verify response có đủ field spec không", "kiểm tra dev áp dụng công thức đúng chưa", "convert testcase manual sang auto", "test data-driven từ csv", "compare response với spec".
  [SKIP] Không dùng nếu user chưa có spec (chỉ có endpoint URL trần) hoặc chưa có test case manual — gợi ý họ chuẩn bị trước. Không dùng cho UI test / unit test.
---

# api-autotest-from-spec

Skill này build autotest **spec-driven**: tài liệu API là source-of-truth cho schema, file test case manual là source-of-truth cho scenario. Output có 3 lớp validate:

1. **Schema** — mọi field bắt buộc + format + enum
2. **Business rule** — công thức tính (vd weighted avg, threshold) tự compute và so sánh
3. **Scenario** — từng TC manual map 1-1 sang Robot/CSV

## Quy trình 7 bước

### Step 1 — Gather sources

Hỏi/tìm 3 thứ:

| Source | Format | Mục đích |
|---|---|---|
| **API spec** | PDF / Markdown / Postman collection / OpenAPI / Swagger | Schema, enum, format, business rule |
| **Manual TC** | xlsx / csv / markdown table | Scenario, expected status, edge cases |
| **Files mẫu** | jpg/png/pdf/json... | Input cho TC (positive + negative) |

Auto-detect trong cwd:

```bash
find . -maxdepth 3 \( -name "*.pdf" -o -name "*postman*" -o -name "openapi*" -o -name "swagger*" \) -not -path "*/node_modules/*"
find . -maxdepth 4 -name "testcase*.xlsx" -o -name "*_testcase*.csv"
```

Nếu thiếu spec → STOP, yêu cầu user cung cấp. Spec là điều kiện tiên quyết.

### Step 2 — Extract schema từ spec

Đọc spec và trích ra **bảng schema** cho mỗi endpoint, trả lời 4 câu:

1. **Top-level fields bắt buộc?** (list rõ kèm type)
2. **Có nested object/array không?** (vd `result`, `items[]`, `key_value_pairs[]`) → mỗi nested cũng có schema riêng
3. **Enum/format?** (status enum, UUID, ISO 8601, SHA-256, MIME whitelist, ...)
4. **Business rule công thức?** (vd weighted avg, threshold logic) — hỏi user nếu spec không nói rõ

Output dạng bảng audit:

```
| Field | Required | Type | Format/Enum | Note |
|---|---|---|---|---|
| request_id | ✓ | string | UUID v4 | top-level |
| status | ✓ | string | enum {done, processing, failed} | |
| result.overall_confidence | ✓ | number 0-1 | weighted avg | R3 formula |
| result.key_value_pairs[].key | ✓ | string | | item-level |
```

### Step 3 — Audit gap (nếu đã có test trước đó)

Đối chiếu **schema spec** vs **assertion code hiện tại**. Báo cáo:
- ❌ Field thiếu hoàn toàn
- ⚠️ Field check sai path (vd check ở top-level nhưng spec đặt trong `result`)
- ⚠️ Tên field sai (vd code dùng `warning`, spec là `warnings`)
- ⚠️ Enum chưa đủ giá trị

### Step 4 — Quyết định partition (CSV vs Individual Robot)

| Pattern | Style | Lý do |
|---|---|---|
| Lặp pattern, chỉ khác input → output (vd field validation, MIME whitelist, error codes) | **CSV + DataDriver** | Dễ thêm TC, ít boilerplate |
| Mỗi TC có assertion riêng (functional positive, document-type-specific keys) | **Individual** | Cần check field cụ thể |
| Flow phức tạp (cache, replay, IDOR, SQLi, mass-assignment) | **Individual** | Setup đặc biệt |
| Cần multipart raw / 2 file / custom header | **Individual** suite phụ (`*_flows.robot`) | DataDriver không support |

Quy ước đặt tên file:
```
tests/<api_group>/
├── 01_functional.robot
├── 02_edge_case.robot              ← DataDriver CSV
├── 02b_edge_flows.robot            ← Individual cho flow phức tạp
├── 03_business_rules.robot
├── 04_field_validation.robot       ← DataDriver CSV
├── 04b_field_validation_flows.robot
└── 05_security.robot
```

### Step 5 — Scaffold cấu trúc

Layout chuẩn (xem `templates/structure.md`):

```
PROJECT/
├── env/
│   ├── .env                              # BASE_URL, API_KEY, MODE
│   └── load_env.py
├── variables/
│   └── <api>_variable.robot              # paths, enums, thresholds, formula coefficients
├── resources/
│   ├── keywords/
│   │   ├── common/
│   │   │   └── common_actions.robot      # Create Session (with/without auth, custom header)
│   │   └── <api>/
│   │       ├── <api>_actions.robot       # Low-level: Call POST X, Call GET Y
│   │       ├── <api>_flows.robot         # Business flows (multi-step scenarios)
│   │       └── assertion_helpers.robot   # Schema validators + business rule validators
│   └── test_cases/
│       ├── <api>_*_data.csv              # DataDriver CSV
│       └── files/                        # Sample input files
├── tests/<api>/                          # Suites (split per spec section)
├── mock_server/                          # (optional) Flask mock vendor
├── scripts/                              # generate_edge_files, build_golden_expected
└── requirements.txt
```

### Step 6 — Viết schema validator

3 lớp validator, gọi bằng **1 keyword tổng**:

```robot
Verify <Endpoint> Response Schema
    [Arguments]    ${body}
    Verify <Endpoint> Top Level Schema    ${body}
    Verify <Nested> Schema    ${body}[<nested_key>]      # nếu có
    FOR    ${item}    IN    @{body}[<array_key>]         # nếu có array
        Verify <Item> Schema    ${item}
    END
```

Mỗi sub-validator:
- Loop qua `@{required}` list → `Dictionary Should Contain Key` (kèm message rõ tên field)
- Type check: `Should Be True    isinstance($x, <type>)`
- Format check: `Verify UUID v4`, `Verify ISO 8601 Timestamp`, `Verify SHA256 Hash`
- Enum check: `List Should Contain Value`
- Cross-ref check: vd `result.request_id` phải trùng top-level `request_id`

Format helpers chuẩn (copy nguyên — luôn dùng được):

```robot
Verify UUID v4
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$

Verify SHA256 Hash
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^[0-9a-f]{64}$

Verify ISO 8601 Timestamp
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}([.,]\\d+)?(Z|[+-]\\d{2}:?\\d{2})$
```

### Step 7 — Viết business-rule validator (formula)

Khi spec mô tả **công thức** (weighted average, threshold, conditional logic), viết 2 keyword:

```robot
Calculate Expected <Rule>
    [Documentation]    Implement đúng công thức trong spec.
    [Arguments]    ${body}
    # ... compute expected value via Evaluate ...
    RETURN    ${expected}

Verify <Rule>
    [Arguments]    ${body}    ${tolerance}=0.01
    ${expected}=    Calculate Expected <Rule>    ${body}
    ${actual}=      Set Variable    ${body}[<path_to_field>]
    ${diff}=        Evaluate    abs(${actual} - ${expected})
    Should Be True    ${diff} <= ${tolerance}
    ...    <field>=${actual} không khớp công thức (expected=${expected}, diff=${diff})
```

**Sanity check**: chạy thử công thức trên response example trong spec (nếu có) — kết quả phải khớp.

Áp dụng validator trên **dataset đa dạng** (≥3 loại file khác nhau) để bắt dev áp dụng formula sai chỉ cho 1 loại doc.

Xem ví dụ chi tiết: `templates/formula_validator.robot`.

## Decision rules (anti-pattern)

### KHÔNG dùng skill này khi:
- Spec mơ hồ / chưa stable → tests sẽ break liên tục, chờ spec lock
- API trả response dạng plain text / HTML / binary → schema validator không có nghĩa
- Chỉ test 1-2 endpoint đơn giản → overhead không bù được, viết tay nhanh hơn

### Tránh các pitfall:
1. **Hardcode value cụ thể trong assertion** (vd `Should Be Equal "Vương Tiểu Minh"`). Thay bằng:
   - Regex pattern (vd `[\\u4e00-\\u9fff]` cho Hán tự)
   - Logical check (vd `Should Not Be Empty`, `Should Not Be Equal As Strings ${expected} ${original}`)
2. **Schema check ở sai path**: nếu spec đặt field trong nested object, phải navigate đúng. Helper nên **tự navigate** để TC không lặp `${body}[result][...]`.
3. **Strict enum khi spec mở** (vd "vi, en, ru, ..."). Dùng list `LANGUAGE_VALUES` nhưng cho phép extend.
4. **Optional field**: chỉ check khi điều kiện đúng (vd `translation_vi` chỉ assert khi `language != vi`).
5. **Field naming inconsistency**: spec dùng `warnings` (plural) — code dùng `warning` (singular) → fail silently. Đọc spec kỹ.
6. **Base URL path conflict**: nếu `.env BASE_URL` đã include path prefix (vd `/api/v1/ocr`), code paths phải relative (`/extract`) — không lặp prefix.

## Quy ước naming

| Component | Pattern |
|---|---|
| Action keyword | `Call <METHOD> <PATH>` (vd `Call POST /ocr/extract`) |
| Flow keyword | Verb-object (vd `Upload And Verify Document`) |
| Schema validator | `Verify <Endpoint> Response Schema` / `Verify <Object> Schema` |
| Business rule | `Verify <RuleName>` + `Calculate Expected <RuleName>` |
| Variable enum | `@{<DOMAIN>_VALUES}` (vd `@{LANGUAGE_VALUES}`) |
| Threshold const | `${<RULE>_<BOUND>}` (vd `${CONFIDENCE_FIELD_HIGH}`) |
| CSV data file | `<endpoint>_<category>_data.csv` (vd `extract_field_validation_data.csv`) |
| Test suite | `<NN>_<category>.robot` + optional `<NN>b_<category>_flows.robot` |

## Tham khảo

- `templates/structure.md` — layout đầy đủ
- `templates/assertion_helpers_template.robot` — schema + format + business rule helpers
- `templates/formula_validator.robot` — pattern compute & compare
- `templates/csv_datadriven.robot` — pattern data-driven
- `references/spec_audit_checklist.md` — checklist khi đọc spec
