# scaffold-api-test (user-level skill)

Skill portable dùng cho mọi dự án trên máy. Auto-detect framework (Robot Framework / pytest / ...) và sinh test scaffold theo convention dự án đang mở.

## Khác biệt vs project skill

- **Project skill** (`<project>/.claude/skills/write-api-test/`): hard-code convention 1 dự án cụ thể (vd dự án Baokim hard-code `${LOGIN_SESSION}`, `Build Signed Auth Headers`, schema CSV cố định)
- **User skill** (this): KHÔNG assume gì. Detect framework → đọc convention dự án → hỏi user info API → sinh file.

## Cấu trúc

```
~/.claude/skills/scaffold-api-test/
├── SKILL.md                          ← Claude đọc khi invoke
├── README.md                         ← file này
├── templates/
│   ├── robotframework/
│   │   ├── actions.robot.tmpl        ← keyword cấp thấp
│   │   ├── flows.robot.tmpl          ← keyword cấp cao + assertion
│   │   ├── test_suite.robot.tmpl     ← suite data-driven với DataDriver
│   │   └── test_data.csv.tmpl        ← CSV với pattern tiêu chuẩn
│   └── pytest/
│       ├── conftest.py.tmpl          ← fixtures (base_url, api_client, auth_token)
│       └── test_module.py.tmpl       ← test data-driven với parametrize
└── references/
    └── test-patterns.md              ← bảng pattern test field-level chuẩn
```

## Cách Claude dùng

1. User nói "viết test cho POST /foo" → skill auto-activate
2. Skill detect framework dự án (đọc `requirements.txt` / `package.json`)
3. Skill đọc 1 file test mẫu trong dự án để học style
4. Skill hỏi user: module name, endpoint, fields, baseline payload
5. Skill đọc template trong `templates/<framework>/`, substitute placeholder, ghi file vào path phù hợp dự án
6. Skill dry-run kiểm syntax
7. Skill báo cáo file + env cần điền

## Mở rộng

Thêm framework mới? Tạo `templates/<framework_name>/` với template tương đương:
- 1 file định nghĩa low-level API call
- 1 file/section build payload + assert
- 1 file/format data-driven (CSV / parametrize / each)
- 1 file test runner

Cập nhật Step 1 của `SKILL.md` để thêm rule detect framework đó.

## Override per-project

Nếu 1 dự án cần convention riêng (vd Baokim với sign + encrypt), tạo project skill ở `<project>/.claude/skills/write-api-test/` với same / similar tên. Project skill được ưu tiên hơn user skill khi cả 2 cùng tồn tại.
