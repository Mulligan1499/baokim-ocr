# Progressive Disclosure pattern — convention cho `.claude/skills/`

Pattern Anthropic recommend để skill load không cạn token context. Áp dụng khi SKILL.md > 250 lines hoặc có nhiều examples/templates lookup.

## Structure chuẩn

```
.claude/skills/<skill-name>/
├── SKILL.md                       (≤ 250 lines — load mỗi khi skill match)
│   ├── frontmatter                (metadata: triggers/anti-triggers)
│   ├── Mục đích                   (1 paragraph)
│   ├── Khi nào dùng / không dùng  (decision matrix)
│   ├── Workflow                   (CRITICAL checklist — Supabase nguyên tắc 2)
│   ├── What NOT to do             (anti-patterns)
│   ├── Cost-aware notes
│   └── Maintenance / Roadmap
└── references/                    (lazy-load — chỉ khi cần lookup)
    ├── templates.md               (prompt skeletons + schemas)
    ├── examples.md                (anchor examples positive/negative)
    └── taxonomies/                (optional — domain-specific lookup tables)
```

## Nguyên tắc

1. **CRITICAL checklist phải ở SKILL.md chính** — không hide trong reference (Supabase: "cái gì có thể bị bỏ qua, sẽ bị bỏ qua")
2. **SKILL.md self-contained** — đọc xong là biết workflow, không bắt buộc phải load references
3. **References = lookup material** — đọc khi cần copy/adapt cụ thể, không phải kiến thức nền
4. **Cross-link rõ ràng** — SKILL.md có pointer "see [references/X.md](references/X.md)" dạng markdown link

## Skill đã áp dụng

| Skill | SKILL.md size | References |
|---|---|---|
| dev-vision-prompt-designer | 212 lines (-46%) | templates.md, examples.md |
| dev-baokim-api-design | 284 lines (born compliant) | templates.md (327 lines), examples.md (156 lines) |

## Skill TODO split (size > 300 lines, future work)

- `cross-llm-extraction-prompt` — 327 lines, có thể split examples
- `dev-baokim-laravel-repo-pattern` — 486 lines (largest, ưu tiên)
- `dev-baokim-sql-reviewer` — 396 lines
- `ocr-confidence-aggregator` — 340 lines (split weights tuning table)
- `feedback-to-skill-refiner` — 313 lines (split decision tree → references/decision-tree.md)
- `ocr-extraction-validator` — 323 lines

## Khi nào KHÔNG split

- Skill < 250 lines — overhead reference không đáng
- Workflow ngắn nhưng critical checklist dài — KEEP trong SKILL.md
- Skill mới (lifecycle draft) — chờ stable rồi split

## Reference

- Anthropic — Don't Build Agents, Build Skills Instead (Barry Zhang & Mahesh Murag)
- Supabase — Combine Skills and MCP to Close the Context Gap (Pedro Rodrigues)
