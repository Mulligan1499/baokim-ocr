# CSV format cho DataDriver

## Cấu trúc

Hàng 1: header, cột đầu là `*** Test Cases ***`, các cột tiếp theo là argument truyền cho template.

```csv
*** Test Cases ***,tc_id,scenario,api_key_value,file_path,mime_type,expected_status,expected_error_code
TC-50 Missing X-API-Key,TC-50,missing_key,,${FILES_DIR}/cccd.png,,401,UNAUTHORIZED
TC-51 X-API-Key sai,TC-51,wrong_key,invalid_xxx,${FILES_DIR}/cccd.png,,401,UNAUTHORIZED
TC-58 MIME image/jpeg hợp lệ,TC-58,mime_valid,${API_KEY},${FILES_DIR}/cccd.jpg,image/jpeg,200,
TC-62 File .docx không hỗ trợ,TC-62,format_reject,${API_KEY},${FILES_DIR}/doc.docx,,400,INVALID_FILE_FORMAT
```

## Quy tắc

1. **Cột đầu** = tên TC (sẽ hiển thị trong report). Format: `<TC_ID> <description>`.
2. **Cột tiếp theo** map 1-1 với `[Arguments]` của template keyword.
3. **Variable expansion** trong CSV: dùng `${FILES_DIR}`, `${API_KEY}`, ... — Robot resolve khi runtime.
4. **Field rỗng**: 2 dấu phẩy liền `,,` → truyền string rỗng. Robot tự nhận `${EMPTY}`.
5. **Field có dấu phẩy trong giá trị**: bọc trong dấu nháy kép `"..."`.
6. **Special chars** (vd `' OR '1'='1`): dùng nháy kép quanh giá trị nếu có dấu nháy đơn.

## Khi dùng / khi không

### NÊN dùng CSV
- Field/header/param validation (cùng template, khác input)
- MIME whitelist test
- Boundary test (file 0 byte, exact 10MB, +1 byte)
- Error code mapping (mỗi error code 1 input)

### KHÔNG dùng CSV — viết Individual Robot
- TC có flow phức tạp (cache, replay, 2 user)
- Mỗi TC assert field cụ thể (vd TC-12 passport check 7 keys khác TC-2 GPKD check 4 keys)
- Cần setup riêng (vd mock vendor force 5xx)
- Cần multipart raw, 2 file, custom Content-Type

## Tách suite

Khi 1 group có cả CSV + flow phức tạp, tách 2 file:
- `04_field_validation.robot` ← DataDriver CSV
- `04b_field_validation_flows.robot` ← Individual cho TC cần multipart raw

Đặt tên file suite có hậu tố `b` để giữ thứ tự sort.

## Template keyword tương ứng

```robot
*** Settings ***
Library          DataDriver    ${CURDIR}/.../endpoint_field_validation_data.csv
Test Template    Run Field Validation

*** Keywords ***
Run Field Validation
    [Arguments]    ${tc_id}    ${scenario}    ${api_key_value}    ${file_path}    ${mime_type}    ${expected_status}    ${expected_error_code}=${EMPTY}
    Set Test Documentation    ${tc_id} | scenario=${scenario}
    IF    '${scenario}' == 'missing_key'
        Create Session Without Auth    ${tc_id}
    ELSE
        Create Session With Custom Header    ${tc_id}    ${api_key_value}
    END
    ${mime}=    Set Variable If    '${mime_type}' == '${EMPTY}'    ${None}    ${mime_type}
    ${resp}=    Call POST /endpoint    ${file_path}    alias=${tc_id}    mime_type=${mime}    expected_status=${expected_status}
    IF    ${expected_status} == 200
        Verify <Endpoint> Response Schema    ${resp.json()}
    ELSE IF    '${expected_error_code}' != '${EMPTY}'
        Verify Error Schema    ${resp.json()}    ${expected_error_code}
    END

*** Test Cases ***
Placeholder
    placeholder    placeholder    ${EMPTY}    placeholder    ${EMPTY}    400    ${EMPTY}
```
