*** Settings ***
Documentation    Template assertion helpers — copy/đổi tên cho endpoint mới.
...              3 lớp: Schema validators + Format validators + Business-rule validators.
Library          Collections
Library          String
Library          DateTime
Resource         ../../variables/<api>_variable.robot


*** Keywords ***
# ============================================================================
# SCHEMA VALIDATION — gọi 1 lần check hết
# ============================================================================

Verify <Endpoint> Response Schema
    [Documentation]    Tổng validator: top-level + nested + item-level.
    [Arguments]    ${body}
    Verify <Endpoint> Top Level Schema    ${body}
    # Nếu có nested object (vd "result"):
    Verify <Endpoint> Result Schema    ${body}[result]    ${body}[request_id]
    # Nếu có array items (vd "key_value_pairs"):
    FOR    ${item}    IN    @{body}[result][<array_key>]
        Verify <Item> Schema    ${item}
    END

Verify <Endpoint> Top Level Schema
    [Documentation]    Field bắt buộc cấp top theo spec.
    [Arguments]    ${body}
    @{required}=    Create List    request_id    status    document_id    file_name
    ...    file_hash    file_size_bytes    mime    uploaded_at    uploaded_by    cached    result
    FOR    ${field}    IN    @{required}
        Dictionary Should Contain Key    ${body}    ${field}    Top-level thiếu field: ${field}
    END
    # Type + format checks
    Verify UUID v4    ${body}[request_id]
    List Should Contain Value    ${STATUS_VALUES}    ${body}[status]
    Should Be True    isinstance($body['document_id'], int)
    Should Not Be Empty    ${body}[file_name]
    Verify SHA256 Hash    ${body}[file_hash]
    Should Be True    ${body}[file_size_bytes] >= 0
    List Should Contain Value    ${MIME_WHITELIST}    ${body}[mime]
    Verify ISO 8601 Timestamp    ${body}[uploaded_at]
    Should Not Be Empty    ${body}[uploaded_by]
    Should Be True    isinstance($body['cached'], bool)
    Should Be True    isinstance($body['result'], dict)

Verify <Endpoint> Result Schema
    [Documentation]    Field trong nested object.
    [Arguments]    ${result}    ${parent_request_id}=${EMPTY}
    @{required}=    Create List    request_id    <list_required_fields>
    FOR    ${field}    IN    @{required}
        Dictionary Should Contain Key    ${result}    ${field}    Result thiếu field: ${field}
    END
    # Cross-ref check
    IF    '${parent_request_id}' != '${EMPTY}'
        Should Be Equal As Strings    ${result}[request_id]    ${parent_request_id}
    END
    # Optional fields: chỉ check khi điều kiện đúng
    IF    '${result}[language_detected]' == 'vi'
        Should Be Equal    ${result}[translation_vi]    ${None}
    END

Verify <Item> Schema
    [Documentation]    Field bắt buộc trong array item.
    [Arguments]    ${item}
    @{required}=    Create List    key    value    confidence    flagged
    FOR    ${field}    IN    @{required}
        Dictionary Should Contain Key    ${item}    ${field}
    END
    Should Not Be Empty    ${item}[key]
    Should Be True    isinstance($item['value'], str)
    Should Be True    0 <= ${item}[confidence] <= 1
    Should Be True    isinstance($item['flagged'], bool)


# ============================================================================
# FORMAT VALIDATORS — copy nguyên, luôn dùng được
# ============================================================================

Verify UUID v4
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$

Verify SHA256 Hash
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^[0-9a-f]{64}$

Verify ISO 8601 Timestamp
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}([.,]\\d+)?(Z|[+-]\\d{2}:?\\d{2})$

Verify Email Format
    [Arguments]    ${value}
    Should Match Regexp    ${value}    ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$


# ============================================================================
# CONVENIENCE GETTERS / ASSERTIONS — nhận ${body} root, tự navigate
# ============================================================================

Verify Field Equals
    [Documentation]    Generic: ${body}[<path>] == ${expected}. Path dùng . hoặc nested via [].
    [Arguments]    ${body}    ${path}    ${expected}
    ${actual}=    Evaluate    $body${path}
    Should Be Equal As Strings    ${actual}    ${expected}

Get Item From Array By Key
    [Documentation]    Tìm item đầu tiên match key trong array.
    [Arguments]    ${array}    ${match_field}    ${match_value}
    FOR    ${item}    IN    @{array}
        IF    '${item}[${match_field}]' == '${match_value}'    RETURN    ${item}
    END
    Fail    Không tìm thấy item có ${match_field}=${match_value}


# ============================================================================
# ERROR SCHEMA — chuẩn cho mọi 4xx/5xx response
# ============================================================================

Verify Error Schema
    [Documentation]    Error response schema chuẩn.
    [Arguments]    ${response_json}    ${expected_error_code}=${EMPTY}
    @{required}=    Create List    error_code    message_vi    message_en    request_id    retry_after
    FOR    ${field}    IN    @{required}
        Dictionary Should Contain Key    ${response_json}    ${field}
    END
    Run Keyword If    '${expected_error_code}' != '${EMPTY}'
    ...    Should Be Equal As Strings    ${response_json}[error_code]    ${expected_error_code}
