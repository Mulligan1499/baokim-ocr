# QA Skills

> Folder reserved cho team **QA / Tester** đẩy skill vào đây.

## Hướng dẫn add skill mới

### Bước 1 — Tạo folder theo naming convention

Folder tên dạng `qa-<area>-<purpose>`:
- `qa-test-case-designer/` — Sinh test case từ AC
- `qa-bug-report-template/` — Template báo cáo bug có structured
- `qa-regression-suite-planner/` — Plan regression suite cho feature
- ... (tự đặt theo nghiệp vụ QA)

### Bước 2 — Tạo file SKILL.md trong folder

Frontmatter template (Hybrid format — Anthropic core + Baokim extensions):

```yaml
---
name: qa-<area>-<purpose>
description: <1 câu cô đọng, ≤300 chars>
when_to_use: |
  Triggers: "<trigger phrase 1>", "<trigger phrase 2>", ...

  Anti-triggers (KHÔNG dùng skill này khi):
  - <case 1>
  - <case 2>
# Baokim enterprise extensions:
owner: <your-email>@baokim.vn
version: 0.1.0
lifecycle: draft
domain: qa
created: YYYY-MM-DD
updated: YYYY-MM-DD
tags: [qa, ...]
---

# <Skill Name>

## Mục đích
...

## Khi nào dùng / không dùng
...

## Workflow
### Step 1: ...
### Step 2: ...

## Examples
### Example 1: <concrete case>

## What NOT to do
❌ ...
```

### Bước 3 — Update catalog README

Sau khi add SKILL.md, cập nhật catalog table trong file `README.md` (file này) — thêm hàng với mô tả ngắn.

### Bước 4 — Verify

```bash
# Check YAML parse OK
head -1 qa-<name>/SKILL.md  # Expect: ---

# Check description ≤ 300 chars
awk '/^description:/{print length($0); exit}' qa-<name>/SKILL.md
```

## Catalog (chờ team QA add)

| # | Skill | Phase | Mô tả ngắn |
|---|---|---|---|
| _ | _Pending — QA team add here_ | _ | _ |

## ⚠️ Lưu ý quan trọng

Theo Anthropic spec, skill chỉ auto-load khi ở **flat path** `.claude/skills/<name>/SKILL.md`. Skills nằm trong subfolder `QA/<name>/SKILL.md` **không auto-load**.

Để skill activate được trong Claude Code local, có 2 cách:
1. **Tạm thời di chuyển ra flat khi cần activate**: `cp -r .claude/skills/QA/<name> .claude/skills/`
2. **Manual invoke với full path** (chưa rõ Claude Code support)

→ Cho mục đích submission BGK (review markdown), nested structure OK. Cho dev local hoạt động hằng ngày, cân nhắc flat path.

## Reference

Xem [`../README.md`](../README.md) cho overview ecosystem + extension guide đầy đủ.
