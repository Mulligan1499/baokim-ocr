# E2E Flow Detection Guide

Hướng dẫn detect test case loại E2E flow (nhiều API kết hợp) để SKIP không gen vào CSV.

---

## 1. Tại sao phải skip E2E flow?

CSV pattern là **single-field validation** — mỗi row test 1 field cụ thể trên 1 API endpoint. E2E flow gồm nhiều API call có thứ tự, không fit pattern này.

Ép E2E vào CSV sẽ:
- Mất context về thứ tự call
- Mất chia sẻ state giữa các bước (vd `order_id` từ B1 cần truyền vào B2)
- Mất assertion intermediate (mỗi bước success thì sang bước tiếp)
- Template auto test sẽ generate sai

→ E2E TC phải viết tay trong file `.robot` (xem skill `robot-script-writer`).

---

## 2. Tín hiệu detect E2E flow

### 2.1 Tín hiệu MẠNH (confidence ≥ 90% → skip ngay)

#### A. Description chứa từ khóa E2E

| Từ khóa (tiếng Việt) | Từ khóa (tiếng Anh) |
|---|---|
| "quy trình", "luồng nghiệp vụ", "chuỗi" | "end-to-end", "E2E", "workflow" |
| "đầy đủ", "hoàn chỉnh" | "full flow", "complete flow" |
| "tích hợp", "kết hợp" | "integration flow", "chained" |
| "kịch bản tổng" | "scenario test" |

Examples:
- ✅ "Quy trình thanh toán đầy đủ từ tạo order đến capture"
- ✅ "End-to-end flow: register → login → create order"
- ✅ "Luồng nghiệp vụ refund đơn đã thanh toán"
- ✅ "Tích hợp create order và payment"

#### B. Test Steps có ≥ 2 API call khác endpoint

Detect bằng regex:

```regex
(POST|GET|PUT|DELETE|PATCH)\s+(/[a-zA-Z0-9/\-_{}]+)
```

Count unique endpoints trong cả Test Steps. Nếu ≥ 2 → MẠNH signal E2E.

Examples:
- ✅ "B1: POST /orders → B2: POST /payments" (2 endpoints khác nhau)
- ✅ "Step 1: Call POST /auth/login. Step 2: Call POST /orders with token. Step 3: Call GET /orders/{id}" (3 endpoints khác nhau)
- ❌ "POST /login với username=abc, sau đó POST /login với password=xyz" (cùng endpoint /login — KHÔNG phải E2E)

#### C. Test Steps có sequence markers

| Marker (tiếng Việt) | Marker (tiếng Anh) |
|---|---|
| "Bước 1, Bước 2, Bước 3" | "Step 1, Step 2, Step 3" |
| "B1, B2, B3" | "S1, S2, S3" |
| "1)... 2)... 3)..." | "1)... 2)... 3)..." |
| "Đầu tiên..., Sau đó..., Cuối cùng..." | "First..., Then..., Finally..." |

Nếu có ≥ 3 markers + mỗi bước có API call khác nhau → MẠNH signal E2E.

### 2.2 Tín hiệu YẾU (confidence 50-80% → flag warning, hỏi user)

#### D. Test Steps mention state transition

| Phrase | Indicator |
|---|---|
| "chuyển trạng thái từ X sang Y" | Có thể là state machine test |
| "sau khi capture", "sau khi pay" | Cần state setup trước |
| "verify status đã chuyển" | Multi-step state |

Examples:
- ⚠️ "Verify order chuyển status từ PENDING sang CAPTURED sau khi thanh toán"
- ⚠️ "Cancel order sau khi đã capture (phải reject)"

→ Có thể là E2E flow hoặc single-field test với setup phức tạp. Hỏi user.

#### E. Test Steps mention precondition phức tạp

| Phrase | Indicator |
|---|---|
| "đã có order trước đó" | Cần setup state |
| "với order đã capture" | Cần chain action |
| "sau khi login" | Cần auth setup |

⚠️ "Sau khi login" alone KHÔNG phải E2E — đó là Suite Setup. Nhưng "Sau khi login VÀ tạo order" → có thể E2E.

### 2.3 Tín hiệu CONFLICT (KHÔNG phải E2E mặc dù trông giống)

#### F. Pattern lặp lại trên cùng endpoint

```
"Gọi POST /login lần 1 với credential A, sau đó gọi POST /login lần 2 với credential B"
```

→ Cùng endpoint `/login` × 2 lần. Đây là **idempotency test** hoặc **replay test**, vẫn là single-endpoint. Có thể vào CSV.

Nhưng nếu là **idempotency check phức tạp** (cần verify response 2 lần identical) → có thể cần flow keyword. Flag warning.

#### G. Test Steps có nhiều assertion intermediate

```
"POST /orders → verify status_code=201 → verify response có order_id → query DB → verify record tồn tại"
```

→ Đây là single-endpoint test (POST /orders) + post-call assertion (DB check). KHÔNG phải E2E flow.

Pattern này skill xử lý được: gen CSV với 1 row, expected_code=201, sentinel field rỗng = happy path. Phần DB verify tự động làm trong template (3-way compare).

---

## 3. Decision algorithm

```python
def detect_e2e_flow(description, test_steps):
    score = 0
    reasons = []

    # Signal A: keyword
    if matches_e2e_keyword(description):
        score += 50
        reasons.append("Description chứa từ khóa E2E")

    # Signal B: multiple endpoints
    endpoints = extract_endpoints(test_steps)
    unique_endpoints = set(endpoints)
    if len(unique_endpoints) >= 2:
        score += 40
        reasons.append(f"Test Steps có {len(unique_endpoints)} endpoints khác nhau")

    # Signal C: sequence markers
    if has_sequence_markers(test_steps) and len(unique_endpoints) >= 2:
        score += 30
        reasons.append("Test Steps có sequence markers + multi-endpoint")

    # Signal D: state transition (weak)
    if has_state_transition_phrase(test_steps):
        score += 20
        reasons.append("Test Steps mention state transition")

    # Signal E: complex precondition (weak)
    if has_complex_precondition(test_steps):
        score += 15
        reasons.append("Có precondition phức tạp")

    # Conflict signals (giảm score)
    if same_endpoint_repeated(endpoints):
        score -= 30
        reasons.append("Repeated calls trên cùng endpoint — có thể là idempotency test")

    # Decision
    if score >= 70:
        return "E2E", reasons       # skip, ghi vào warnings
    elif score >= 40:
        return "AMBIGUOUS", reasons # hỏi user
    else:
        return "SINGLE_FIELD", []   # gen vào CSV
```

---

## 4. Output cho E2E TC bị skip

Ghi vào `<feature>_warnings.md`:

```markdown
# E2E Flow Test Cases — Cần viết tay

Skill đã skip các test case sau vì là E2E flow.
Anh cần viết tay trong file `.robot` theo pattern flow keyword.

Xem skill `robot-script-writer` để gen .robot E2E test suite.

---

## TC_E2E_001 — Tạo order và thanh toán đầy đủ

**Description:** Quy trình thanh toán đầy đủ từ tạo order đến capture

**Test Steps (từ Excel):**
- B1: POST /auth/login với credential hợp lệ
- B2: POST /orders với amount=100000, currency=VND
- B3: POST /payments với order_id từ B2
- B4: POST /payments/{payment_id}/capture
- B5: GET /orders/{id} verify status=CAPTURED

**Expected Result:** Order chuyển status PENDING → AUTHORIZED → CAPTURED

**Lý do skip:** Description chứa từ khóa E2E ("Quy trình ... đầy đủ"); Test Steps có 4 endpoints khác nhau (/auth/login, /orders, /payments, /payments/capture); sequence markers (B1-B5)

**Đề xuất flow keyword:**
```robotframework
Complete Payment Flow Successfully
    [Arguments]    ${amount}=100000    ${currency}=VND
    Login And Get Token
    ${order_id}=    Create Order Successfully    amount=${amount}    currency=${currency}
    ${pay_response}=    Call Make Payment API    ${order_id}    expected_status=201
    ${payment_id}=    Set Variable    ${pay_response.json()}[payment_id]
    ${cap_response}=    Call Capture Payment API    ${payment_id}    expected_status=200
    Should Be Equal As Strings    ${cap_response.json()}[status]    CAPTURED
    ${order_resp}=    Call Get Order API    ${order_id}
    Should Be Equal As Strings    ${order_resp.json()}[status]    CAPTURED
```

---

## TC_E2E_002 — ...
```

---

## 5. AMBIGUOUS case — hỏi user

Khi score 40-70%, hỏi user format này:

```
⚠️ TC ambiguous — không chắc đây là E2E flow hay single-field:

TC_ID: TC_REFUND_005
Description: Refund đơn đã capture với amount partial
Test Steps:
  - Precondition: có payment đã CAPTURED
  - POST /payments/{id}/refund với amount=50000

Reasons:
  - Có precondition phức tạp (precondition "có payment đã CAPTURED")
  - Chỉ 1 endpoint /payments/{id}/refund

Anh muốn:
  [a] Treat as single-field → vào CSV (precondition cần handle ở Setup)
  [b] Treat as E2E flow → skip, viết tay
  [c] Skip TC này hoàn toàn
```

---

## 6. Edge case

### 6.1 Test Steps đơn giản nhưng Description nói E2E

```
Description: "End-to-end test cho login"
Test Steps: "POST /auth/login với credential hợp lệ"
```

→ Description misleading, Test Steps chỉ có 1 endpoint → KHÔNG phải E2E thực sự. Treat as single-field, flag note cho user.

### 6.2 Test Steps nhiều bước nhưng cùng endpoint

```
Test Steps:
  - B1: POST /orders với amount=100000
  - B2: POST /orders với amount=200000
  - B3: POST /orders với amount=300000
```

→ 3 lần cùng endpoint = bulk test, vẫn pattern single-field (mỗi lần test 1 field amount). Gen CSV với 3 rows, KHÔNG skip.

### 6.3 Một số endpoint phụ trợ là Suite Setup

```
Test Steps:
  - Setup: Login để lấy token
  - Step 1: POST /orders với amount=-100
```

→ Login là Suite Setup, KHÔNG phải bước của E2E. Endpoint chính là `/orders`. Treat as single-field.

Detect: phrase "Setup", "Precondition", "Pre-step", "Trước test" → skip endpoint đó khi count.
