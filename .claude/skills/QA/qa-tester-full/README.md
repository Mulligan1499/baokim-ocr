# QA Tester Skill — Hướng dẫn

## Cấu trúc

```
qa-tester/
├── SKILL.md                                    ← Entry point. Claude đọc file này trước.
├── README.md                                   ← File này.
├── references/                                 ← Tài liệu chuyên sâu, đọc khi cần.
│   ├── test-design-techniques.md               6 kỹ thuật thiết kế test (EP, BVA, DT, ST, Pairwise, EG)
│   ├── requirement-doc-to-testcase.md          🆕 BRD/SRS/PRD/User Story/Use Case/mô tả → test case
│   ├── figma-to-testcase.md                    Figma/UI screenshot → test case
│   ├── api-spec-to-testcase.md                 OpenAPI/Postman/cURL → test case
│   ├── db-schema-to-testcase.md                DDL/ERD/migration → test case
│   ├── platform-web.md                         Web testing checklist
│   ├── platform-mobile.md                      Mobile (iOS/Android) testing
│   ├── platform-api.md                         REST/GraphQL API testing
│   ├── platform-database.md                    DB testing (RDBMS/NoSQL/ETL)
│   └── non-functional-testing.md               Performance, Security, Usability, Compatibility
├── templates/                                  ← Mẫu để fill-in.
│   ├── testcase-template.xlsx                  🆕 Template Excel TRỐNG (clone-and-fill)
│   ├── example_login_testcases.xlsx            🆕 File demo Login với 14 TC qua 4 section
│   ├── test-case-template.md                   Mô tả format test case (tham khảo)
│   ├── test-plan-template.md
│   ├── bug-report-template.md
│   ├── test-summary-report-template.md
│   └── traceability-matrix-template.md
└── scripts/                                    🆕 Python scripts
    ├── generate_testcase_xlsx.py               Generate file Excel theo template
    ├── recalc.py                               Recalc formula sau khi generate
    └── office/                                 Helper cho LibreOffice (recalc dependency)
```

## Cách triển khai

### Cách 1: Dùng trực tiếp trong claude.ai (dùng project knowledge)
1. Tạo Project mới trên claude.ai.
2. Upload toàn bộ thư mục `qa-tester/` vào "Project knowledge".
3. Trong system prompt của project, ghi:
   ```
   Bạn là QA Tester 10 năm kinh nghiệm. Khi user nhờ bạn làm việc gì liên quan
   đến QA/Testing, hãy mở SKILL.md trong project knowledge và làm theo workflow
   trong đó. Đọc file references/ tương ứng khi cần chuyên sâu. Dùng template
   trong templates/ khi user yêu cầu test plan, test case, bug report, test
   summary report, hoặc traceability matrix.
   ```
4. Dùng bình thường — Claude sẽ tham chiếu skill khi cần.

### Cách 2: Dùng với Claude Code / API (như Anthropic skill)
- Đặt thư mục vào path skill của tổ chức.
- SKILL.md có YAML frontmatter chuẩn để Claude tự kích hoạt.

### Cách 3: Dùng làm tài liệu nội bộ
- In ra hoặc share Markdown trên Confluence/Notion để team QA có chuẩn chung.

## Mở rộng cho dự án mới

**Không sửa SKILL.md gốc.** Thêm 2 file context riêng cho dự án:

### `project-context.md` (đặt cùng thư mục với SKILL.md hoặc trong project knowledge)
```markdown
# Project Context — [Tên dự án]

## Domain
[Mô tả lĩnh vực: e-commerce, fintech, healthcare...]

## Glossary
- Term A: định nghĩa
- Term B: định nghĩa

## Business rules cốt lõi
- Rule 1: ...
- Rule 2: ...

## Tuân thủ
- [PCI DSS / HIPAA / GDPR / SOC 2 ...]

## Tech stack chính
- Frontend: [...]
- Backend: [...]
- DB: [...]
- Infra: [...]
```

### `project-test-conventions.md`
```markdown
# Test Conventions — [Tên dự án]

## Test case ID format
- Pattern: TC_<MODULE>_<NUM>
- Module codes: LOGIN, ORDER, PAYMENT, ...

## Tools
- Test management: [Jira / TestRail / qTest]
- Bug tracking: [Jira project key]
- Automation: [Playwright / Cypress / ...]

## Severity scale (nếu khác default)
- ...

## Definition of Done cho test
- ...
```

## Khi nào dùng skill nào?

| User hỏi / gửi | Claude sẽ đọc |
|---|---|
| **"Viết test case từ user story / BRD / SRS / mô tả này"** + tài liệu | SKILL.md + requirement-doc-to-testcase.md + test-design-techniques.md → generate Excel |
| "Viết test case từ Figma này" + screenshot | SKILL.md + figma-to-testcase.md + platform-web/mobile.md → generate Excel |
| "Test API này" + OpenAPI/Postman/cURL | SKILL.md + api-spec-to-testcase.md + platform-api.md → generate Excel |
| "Test database/schema này" + DDL | SKILL.md + db-schema-to-testcase.md + platform-database.md → generate Excel |
| "Lập test plan cho release Y" | SKILL.md + test-plan-template.md (+ non-functional-testing.md nếu phức tạp) |
| "Bug này log thế nào?" | SKILL.md + bug-report-template.md |
| "Hôm nay tổng kết test" | SKILL.md + test-summary-report-template.md |
| "Performance test cho login" | SKILL.md + non-functional-testing.md + platform-api.md |

## Pipeline generate Excel

```
[Input: AC / Figma / API / DB] 
   ↓
[Claude phân tích, hỏi clarify nếu cần]
   ↓
[Claude thiết kế sections + test cases trong Python dict]
   ↓
[Gọi generate_testcase_xlsx.create_testcase_workbook(...)]
   ↓
[Recalc formula bằng scripts/recalc.py]
   ↓
[Verify 0 lỗi → Present file cho user]
```

## Versioning skill

Skill này là **v1.0**. Khi cần update:

- **Patch (1.0.x)**: sửa typo, làm rõ wording.
- **Minor (1.x.0)**: thêm reference mới, thêm template, thêm checklist item — không phá vỡ structure cũ.
- **Major (x.0.0)**: thay đổi cấu trúc skill, đổi naming convention — cần migrate guide.

Ghi changelog trong file `CHANGELOG.md` (chưa có ở v1.0, tạo khi có update).

## Đóng góp

Khi áp dụng skill này vào dự án thực tế, các phát hiện có thể fed back để cải tiến:

- Bug pattern mới phát hiện → bổ sung vào "Bug điển hình" của reference tương ứng.
- Tool mới → cập nhật bảng tools.
- Workflow tối ưu mới → cập nhật SKILL.md mục Workflow.
