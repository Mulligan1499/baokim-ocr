*** Settings ***
Documentation    Template cho business-rule validator có công thức.
...              Pattern: Calculate Expected <Rule> + Verify <Rule>.
Library          Collections
Resource         ../../variables/<api>_variable.robot


*** Keywords ***
# ============================================================================
# VÍ DỤ 1 — Weighted average (critical field weight 2×, còn lại 1×)
# ============================================================================

Calculate Expected Weighted Confidence
    [Documentation]    overall = Σ(weight_i × confidence_i) / Σ(weight_i)
    [Arguments]    ${body}
    @{items}=    Get From Dictionary    ${body}[result]    key_value_pairs
    ${sum_weighted}=    Set Variable    ${0}
    ${sum_weights}=     Set Variable    ${0}
    FOR    ${item}    IN    @{items}
        ${is_critical}=    Run Keyword And Return Status
        ...    List Should Contain Value    ${CRITICAL_FIELD_KEYS}    ${item}[key]
        ${weight}=    Set Variable If    ${is_critical}    ${CRITICAL_WEIGHT}    ${NON_CRITICAL_WEIGHT}
        ${sum_weighted}=    Evaluate    ${sum_weighted} + ${weight} * ${item}[confidence]
        ${sum_weights}=     Evaluate    ${sum_weights} + ${weight}
    END
    ${expected}=    Evaluate    ${sum_weighted} / ${sum_weights} if ${sum_weights} > 0 else 0
    RETURN    ${expected}

Verify Weighted Confidence
    [Documentation]    So sánh actual vs expected (tolerance ±${TOLERANCE}). Fail kèm diff message.
    [Arguments]    ${body}    ${tolerance}=${TOLERANCE}
    ${expected}=    Calculate Expected Weighted Confidence    ${body}
    ${actual}=      Set Variable    ${body}[result][overall_confidence]
    ${diff}=        Evaluate    abs(${actual} - ${expected})
    Log    Formula check — actual=${actual}, expected=${expected}, diff=${diff}    INFO
    Should Be True    ${diff} <= ${tolerance}
    ...    overall_confidence=${actual} không khớp công thức weighted (expected=${expected}, diff=${diff} > tolerance ${tolerance})


# ============================================================================
# VÍ DỤ 2 — Threshold logic (3 dải confidence)
# ============================================================================

Verify Field Confidence Threshold
    [Documentation]    ≥0.7 không flag; 0.5–0.7 flagged=true, value vẫn có; <0.5 value="" flagged=true.
    [Arguments]    ${field}
    ${confidence}=    Get From Dictionary    ${field}    confidence
    ${flagged}=       Get From Dictionary    ${field}    flagged
    ${value}=         Get From Dictionary    ${field}    value
    IF    ${confidence} >= 0.7
        Should Be Equal    ${flagged}    ${FALSE}
    ELSE IF    ${confidence} >= 0.5
        Should Be Equal    ${flagged}    ${TRUE}
        Should Not Be Empty    ${value}
    ELSE
        Should Be Equal    ${flagged}    ${TRUE}
        Should Be Equal As Strings    ${value}    ${EMPTY}
        Should Be Equal As Numbers    ${confidence}    0
    END


# ============================================================================
# VÍ DỤ 3 — Coverage ratio (translation phải cover ≥X% raw_text)
# ============================================================================

Verify Translation Coverage
    [Arguments]    ${body}    ${min_ratio}=${TRANSLATION_COVERAGE_MIN}
    ${result}=    Get From Dictionary    ${body}    result
    ${raw}=       Get From Dictionary    ${result}    raw_text
    ${trans}=     Get From Dictionary    ${result}    translation_vi
    Should Not Be Empty    ${trans}
    ${ratio}=    Evaluate    min(1.0, len("""${trans}""")/max(1, len("""${raw}""")))
    Should Be True    ${ratio} >= ${min_ratio}
    ...    Translation coverage ${ratio} < ${min_ratio}


# ============================================================================
# VÍ DỤ 4 — Cross-field consistency (vd expiry_date > date_of_issue)
# ============================================================================

Verify Date Order
    [Documentation]    expiry_date phải sau date_of_issue.
    [Arguments]    ${body}    ${date_from_key}=date_of_issue    ${date_to_key}=expiry_date
    @{pairs}=    Get From Dictionary    ${body}[result]    key_value_pairs
    ${from_item}=    Get Item From Array By Key    ${pairs}    key    ${date_from_key}
    ${to_item}=      Get Item From Array By Key    ${pairs}    key    ${date_to_key}
    ${from_date}=    Convert Date    ${from_item}[value]    date_format=%d.%m.%Y
    ${to_date}=      Convert Date    ${to_item}[value]      date_format=%d.%m.%Y
    Should Be True    '${to_date}' > '${from_date}'
    ...    ${date_to_key}=${to_item}[value] phải sau ${date_from_key}=${from_item}[value]
