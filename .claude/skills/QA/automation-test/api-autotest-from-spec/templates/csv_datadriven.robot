*** Settings ***
Documentation    Template cho test data-driven từ CSV.
...              Dùng khi: nhiều TC lặp pattern (field validation, MIME, error codes).
Library          DataDriver    ${CURDIR}/../../resources/test_cases/<endpoint>_<category>_data.csv
Resource         ../../resources/keywords/common/common_actions.robot
Resource         ../../resources/keywords/<api>/<api>_actions.robot
Resource         ../../resources/keywords/<api>/assertion_helpers.robot
Resource         ../../variables/<api>_variable.robot
Suite Setup      Create Session
Test Template    Run <Category> Validation
Force Tags       <api>    <category>


*** Variables ***
${FILES_DIR}    ${CURDIR}/../../resources/test_cases/files


*** Keywords ***
Run <Category> Validation
    [Documentation]    Template — DataDriver tự thay placeholder args.
    [Arguments]    ${tc_id}    ${scenario}    ${input}    ${expected_status}    ${expected_error_code}=${EMPTY}
    Set Test Documentation    ${tc_id} | scenario=${scenario}
    # Setup session theo scenario
    IF    '${scenario}' == 'missing_auth'
        Create Session Without Auth    ${tc_id}
    ELSE
        Create Session With Custom Header    ${tc_id}    ${input}
    END
    # Dispatch logic
    ${resp}=    Call POST /endpoint    ${input}    alias=${tc_id}    expected_status=${expected_status}
    # Assert
    IF    ${expected_status} == 200
        Verify <Endpoint> Response Schema    ${resp.json()}
    ELSE IF    '${expected_error_code}' != '${EMPTY}'
        Verify Error Schema    ${resp.json()}    ${expected_error_code}
    END


*** Test Cases ***
Placeholder
    [Documentation]    Bị override bởi DataDriver CSV — không thực thi
    placeholder    placeholder    placeholder    400    ${EMPTY}
