---
name: scaffold-api-test
description: |
  [WHAT] Scaffold 1 bộ test API mới cho codebase đang mở. Auto-detect framework (Robot Framework / pytest / khác), đọc convention dự án, sinh file actions + flows + data + test suite theo style đang có.
  [WHEN] User nói: "viết test cho API X", "scaffold endpoint Y", "tạo test data-driven", "add test suite cho POST /foo", "generate test for bar", "bootstrap test cho route", "sinh test cho api".
  [SKIP] Không dùng khi user chỉ hỏi how-to mà KHÔNG kèm endpoint/route cụ thể. Không dùng cho unit test thuần (chỉ scope API/integration test).
---

# scaffold-api-test (portable)

Skill này hoạt động trên **bất kỳ dự án nào** chứ không gắn vào convention cố định. Quy trình:

## Step 1 — Detect framework

Đọc các file marker trong cwd:

```bash
ls package.json requirements.txt pyproject.toml Gemfile pom.xml build.gradle Cargo.toml go.mod 2>/dev/null
```

Quy tắc nhận diện:
- `requirements.txt` chứa `robotframework` → **Robot Framework**
- `pyproject.toml` hoặc `requirements.txt` chứa `pytest` → **pytest**
- `package.json` chứa `@playwright/test` → **Playwright (skip — out of scope)**
- `package.json` chứa `supertest`/`jest` cho API → **Jest+Supertest**
- Không detect được → **HỎI user dùng framework gì**

Cũng kiểm tra:
- Có `.claude/skills/` của project không? Nếu có project skill `write-api-test` (hoặc tương tự) → **defer cho nó** (project-specific luôn ưu tiên hơn)
- Có `CLAUDE.md`, `AGENTS.md`, `README.md` không? Đọc trước để hiểu convention

## Step 2 — Học convention dự án

Tuyệt đối không ASSUME. Tìm ít nhất 1 file test cùng loại để làm reference:

```bash
# Robot
find . -path '*/tests/*' \( -name '*.robot' -o -name '*.txt' \) -not -path '*/venv/*' -not -path '*/node_modules/*' | head -5

# pytest
find . -name 'test_*.py' -not -path '*/venv/*' -not -path '*/.venv/*' | head -5

# Jest
find . -name '*.test.ts' -o -name '*.test.js' -o -name '*.spec.ts' | head -5
```

Đọc 1-2 file đại diện. Ghi nhận:
- **Layout**: actions vs flows tách file? hay 1 file gộp?
- **Naming convention**: snake_case / camelCase / PascalCase / kebab-case
- **Fixtures / Setup**: ở đâu (conftest.py, Suite Setup, beforeAll)
- **Data-driven**: CSV/Excel (DataDriver) / parametrize (pytest) / each (jest)
- **Assertions**: helper function tự viết hay built-in
- **Env loading**: dotenv / framework built-in / shell export
- **Auth pattern**: token cache fixture / setup hook / mỗi test login lại

## Step 3 — Thu thập info API (BẮT BUỘC dùng AskUserQuestion)

Gộp các câu vào 1 lần AskUserQuestion (max 4 câu). Hỏi:

1. **Module name** (kebab-case, vd `refund`, `user-management`)
2. **HTTP method + path** (vd `POST /api/v1/users`)
3. **Base URL** — có sẵn trong env chưa, hay phải thêm var mới?
4. **Auth** — cần token? Lấy từ keyword/fixture nào có sẵn?
5. **Body fields** — list các field + kiểu + bắt buộc/optional
6. **Baseline payload hợp lệ** — example để test happy path
7. **Response shape** — `{code, message, data}`? plain JSON object? array?
8. **Special** — cần sign request? encrypt field? upload file?

Tùy framework đã detect mà có thể bỏ một số câu (vd Robot không cần hỏi response shape vì assert linh hoạt; pytest hỏi vì cần parse dict).

## Step 4 — Sinh file từ template

Templates ở `${SKILL_DIR}/templates/<framework>/`. Đọc → substitute placeholder → ghi vào path phù hợp convention học ở Step 2.

Placeholder phổ biến trong template:
- `{{MODULE}}` — module name (snake_case)
- `{{MODULE_CAMEL}}` — module name (PascalCase, cho class)
- `{{METHOD}}` — HTTP method UPPERCASE
- `{{PATH}}` — endpoint path
- `{{SESSION}}` — session alias (Robot)
- `{{BASELINE_PAYLOAD}}` — example payload hợp lệ
- `{{FIELDS}}` — list fields, đã format theo từng template

**KHÔNG** copy template y nguyên — phải thực sự substitute. Phải check syntax sau khi sinh.

## Step 5 — Sinh CSV/parametrize data với test pattern phổ biến

Đọc `${SKILL_DIR}/references/test-patterns.md` để biết các pattern test field-level tiêu chuẩn (EMPTY, MISSING, SQL injection, boundary, ...). Áp dụng cho từng field bắt buộc của API.

## Step 6 — Verify syntax

- **Robot**: `robot --dryrun --outputdir /tmp/skill-dry <test_file>` (load env trước nếu cần dotenv)
- **pytest**: `pytest --collect-only <test_file>`
- **Jest**: `npx jest --listTests <test_file>` hoặc `--passWithNoTests`

Nếu fail → fix → re-verify. Không bao giờ báo "done" mà chưa verify.

## Step 7 — Báo cáo

Trả về user dưới dạng list ngắn:
- Files đã tạo (đường dẫn clickable `[file](path)`)
- Env vars / fixtures mới cần điền (nếu có)
- Câu lệnh chạy thử cho từng test
- Cảnh báo placeholder (vd: response schema chưa biết, mock data, DB schema chưa khớp)

## Anti-patterns cần tránh

- ❌ Hard-code path không khớp với cấu trúc dự án thật
- ❌ Lặp lại keyword/fixture/helper đã có (luôn grep tìm reuse)
- ❌ Generate file rồi không verify syntax
- ❌ Tạo test happy-path duy nhất, bỏ qua negative cases
- ❌ Skip Step 2 (đọc convention) → sinh ra code style khác hẳn dự án
- ❌ Hỏi quá nhiều câu rời rạc → gộp vào 1-2 lần AskUserQuestion

## Lưu ý mở rộng skill

Khi gặp framework chưa hỗ trợ (vd Karate, REST Assured, Cypress, ...), **đừng giả định cú pháp** — hỏi user link tới 1 file test mẫu của họ rồi học từ đó. Sau đó có thể đề xuất thêm template vào `${SKILL_DIR}/templates/<framework_mới>/` để lần sau dùng lại.
