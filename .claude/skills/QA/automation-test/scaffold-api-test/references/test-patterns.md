# Test patterns chuẩn cho API field validation

Áp dụng cho **mỗi field bắt buộc** trong request body. Sinh đủ các case dưới đây thành rows trong CSV (Robot DataDriver) hoặc params trong `pytest.mark.parametrize`.

## Field-level patterns (mọi field)

| ID suffix | Description                          | test_value sample                              | expected             |
|-----------|--------------------------------------|------------------------------------------------|----------------------|
| `-EMPTY`      | Để trống field                   | `""`                                           | 422 / validation error |
| `-MISSING`    | Bỏ field khỏi body               | (xoá key)                                      | 422                    |
| `-SPACE_ONLY` | Toàn space                       | `"     "`                                      | 422                    |
| `-SPACE_LEAD` | Space đầu/cuối quanh valid value | `" valid "`                                    | (tuỳ — accept hay reject) |
| `-SPACE_MID`  | Space ở giữa value               | `"val ue"`                                     | 422                    |
| `-SPECIAL`    | Ký tự đặc biệt                   | `"@#$%^&*()"`                                  | 422                    |
| `-UNICODE`    | Tiếng Việt có dấu / emoji        | `"BảoKim"` / `"🚀"`                            | (tuỳ)                  |
| `-SQL_BOOL`   | SQL injection boolean-based      | `"' OR '1'='1"`                                | 422                    |
| `-SQL_COMMENT`| SQL injection comment-based      | `"valid' --"`                                  | 422                    |
| `-SQL_STACKED`| SQL injection stacked            | `"valid'; DROP TABLE x; --"`                   | 422                    |
| `-XSS`        | XSS payload                      | `"<script>alert(1)</script>"`                  | 422 / sanitize         |
| `-MAX_OVER`   | Vượt max length                  | `"A"*51` (nếu max=50)                          | 422                    |
| `-MAX_BOUND`  | Đúng max length (boundary)       | `"A"*50`                                       | 200                    |
| `-MIN_BOUND`  | Đúng min length                  | (1 ký tự nếu min=1)                            | 200                    |
| `-NOT_EXIST`  | Value không tồn tại trong DB     | `"NOT_EXIST_XXX"`                              | business error code    |
| `-CASE_UP`    | Case sensitive — uppercase       | `"VALID"`                                      | (tuỳ)                  |
| `-CASE_LOW`   | Case sensitive — lowercase       | `"valid"`                                      | (tuỳ)                  |
| `-CASE_MIX`   | Case sensitive — MixedCase       | `"Valid"`                                      | (tuỳ)                  |

## Numeric / amount field

| ID suffix       | Sample                          | expected        |
|-----------------|---------------------------------|-----------------|
| `-NEGATIVE`     | `-100`                          | 422             |
| `-ZERO`         | `0`                             | (tuỳ rule)      |
| `-MAX_OVERFLOW` | `99999999999999999`             | 422             |
| `-FLOAT`        | `100.5` (nếu field integer)     | 422             |
| `-STRING`       | `"abc"` (nếu field number)      | 422             |
| `-SCIENTIFIC`   | `1e10`                          | (tuỳ parser)    |

## Date / datetime field

| ID suffix         | Sample                          | expected        |
|-------------------|---------------------------------|-----------------|
| `-WRONG_FORMAT`   | `"2026/05/17"` thay vì ISO     | 422             |
| `-INVALID_DATE`   | `"2026-13-45"`                  | 422             |
| `-LEAP_INVALID`   | `"2025-02-29"`                  | 422             |
| `-LEAP_VALID`     | `"2024-02-29"`                  | 200             |
| `-FUTURE_BEYOND`  | `"2099-12-31"` (nếu rule cấm)   | 422             |
| `-PAST_BEYOND`    | `"1900-01-01"`                  | 422 / accept    |
| `-TIMEZONE`       | `"2026-05-17T10:00:00+07:00"`   | 200             |

## Enum / status field

| ID suffix       | Sample                          | expected        |
|-----------------|---------------------------------|-----------------|
| `-VALID_X`      | `"ACTIVE"`                      | 200             |
| `-INVALID_ENUM` | `"FOOBAR"`                      | 422             |
| `-LOWERCASE`    | `"active"`                      | (tuỳ)           |

## Auth / token

| ID suffix       | Sample                          | expected        |
|-----------------|---------------------------------|-----------------|
| `-NO_TOKEN`     | (bỏ Authorization header)       | 401             |
| `-WRONG_TOKEN`  | `"Bearer invalid"`              | 401             |
| `-EXPIRED`      | (token hết hạn)                 | 401             |
| `-WRONG_SCHEME` | `"Basic xxx"`                   | 401             |

## Business-flow cases (cấp request)

| Case                                       | expected        |
|--------------------------------------------|-----------------|
| Happy path baseline                        | 200             |
| Duplicate request (idempotency)            | 200 / 409       |
| Concurrent (race condition)                | (kiểm tra lock) |
| Brute force / rate limit                   | 429 sau N lần   |
| Large body (10MB+)                         | 413             |
| Wrong Content-Type                         | 415             |

## Cách đánh số ID test

Format `[<MODULE>_<FIELD>-<N>]`:
- `[REFUND_AMOUNT-1]` — `amount` field test #1
- `[REFUND_AMOUNT-2]` — test #2
- `[REFUND_ORDER_ID-1]` — `order_id` field test #1
- `[REFUND_FLOW-1]` — business flow test (không gắn 1 field)

Đánh dấu group qua `Tag` column (Robot) hoặc `mark` (pytest): `MODULE|PRIORITY|CATEGORY` ví dụ `REFUND|HIGH|SQL_INJECTION`.
