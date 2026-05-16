# Changelog: dev-baokim-sql-reviewer

Foundation skill review SQL/DDL/Eloquent code theo standards Baokim.

## [0.1.0] - 2026-05-15

### Added

- Initial draft skill
- Workflow 4 steps: classify input → apply rules checklist → violations report → fix proposals
- Coverage 14+ rules:
  - SQR01.1 (M): SELECT * detection
  - SQR01.2 (R): LIMIT OFFSET vs seek pagination
  - SQR02.1 (R): LIKE leading wildcard
  - SQR02.2 (M): IN params > 500
  - SQR03.2 (R): EXPLAIN rows budget
  - SQR04.1 (M): Transaction size limits
  - IDR01-02: Index naming + redundancy + composite size
  - IDR03.1 (M): Partition decision
  - BKM02 (M): Eloquent only
  - BKM04 (M): No query in loop
  - BKM07 (R): Hash search via DB index
- 6 anti-pattern detection sections với before/after code
- 4 examples: N+1 N+1 Repository, missing partition migration, SELECT * query, cross-skill escalation
- Standard violations report format với severity (M/R) + fix proposal + EXPLAIN suggestion

### Reference

- `05-baokim-db-standard.md`: full rule definitions
- `dev-baokim-db-schema-designer`: schema design (overlap on DDL)
- `dev-baokim-laravel-repo-pattern`: code architecture (overlap on Repository)

### Known limitations (v0.1.0)

- Manual review only — chưa có phpstan/larastan integration auto-detect
- Chưa cover subquery anti-patterns (NOT IN, NOT EXISTS performance)
- Chưa cover dangerous DELETE/UPDATE without LIMIT
- Test prompts 8 cases, target 12+ trước active

### Roadmap

- v0.2.0: phpstan/larastan custom rules để CI auto-detect BKM02/04 violations
- v0.3.0: thêm patterns: subquery, dangerous DML, deadlock prone queries
- v1.0.0 (active): production-tested 50+ PR reviews
