# Layout chuẩn cho 1 bộ Robot Framework autotest

```
PROJECT_ROOT/
├── env/
│   ├── .env                              # Local override (GITIGNORE)
│   ├── .env.example                      # Mẫu commit lên repo
│   └── load_env.py                       # Đọc .env → os.environ
│
├── variables/
│   ├── common_variable.robot             # Timeout, retry,...
│   └── <api>_variable.robot              # ⭐ Paths, enums, thresholds, formula coefficients
│
├── resources/
│   ├── keywords/
│   │   ├── common/
│   │   │   └── common_actions.robot      # Create Session (3 kiểu auth: with/without/custom)
│   │   └── <api>/
│   │       ├── <api>_actions.robot       # Low-level: Call POST X, Call GET Y, Call * Raw
│   │       ├── <api>_flows.robot         # Multi-step business flow
│   │       └── assertion_helpers.robot   # ⭐ Schema + business-rule validators
│   │
│   └── test_cases/
│       ├── <endpoint>_<category>_data.csv   # DataDriver CSV
│       ├── testcase_<api>.xlsx              # File manual gốc (source-of-truth scenario)
│       └── files/                            # Sample input files
│           ├── golden_set/                   # File "đúng" (functional positives)
│           ├── adversarial/                  # File edge case (synthetic + thật)
│           └── golden_output/                # *.expected.json (snapshot expected response)
│
├── tests/<api>/
│   ├── <group_1>/
│   │   ├── 01_functional.robot           # Individual (16 TC)
│   │   ├── 02_edge_case.robot            # DataDriver CSV (10 TC lặp pattern)
│   │   ├── 02b_edge_flows.robot          # Individual (5 TC phức tạp)
│   │   ├── 03_business_rules.robot       # Individual + formula validators
│   │   ├── 04_field_validation.robot     # DataDriver CSV
│   │   ├── 04b_field_validation_flows.robot
│   │   └── 05_security.robot
│   ├── <group_2>/
│   │   └── ...
│   └── smoke/
│       └── smoke_real_vendor.robot       # Nightly với real backend
│
├── mock_server/                          # (optional) Flask mock vendor
│   ├── server.py
│   ├── responses/                        # JSON map theo file_hash hoặc request_id
│   ├── docker-compose.yml
│   └── README.md
│
├── scripts/                              # Helper Python
│   ├── generate_edge_files.py            # Sinh file synthetic (0 byte, exact size, fake magic)
│   ├── build_golden_expected.py          # Snapshot expected JSON từ response thật
│   └── seed_data.py                      # (optional) Seed data nếu cần
│
├── requirements.txt
└── README.md                             # Strategy + danh sách file mẫu + TODO
```

## Quy ước con

### `env/.env.example`
```
API_BASE_URL=https://api.example.com/api/v1
API_KEY=replace_me
API_KEY_USER_B=             # cho IDOR/RBAC test (optional)
VENDOR_MODE=mock            # mock | real
MOCK_URL=http://localhost:5000
```

### `variables/<api>_variable.robot`
4 nhóm:
1. **Base**: BASE_URL, API_KEY (read từ env)
2. **Paths**: relative (vd `/extract`) nếu BASE_URL đã có prefix
3. **Enums**: `@{STATUS_VALUES}`, `@{QUALITY_VALUES}`, `@{MIME_WHITELIST}`...
4. **Thresholds/coefficients**: cho business rule formula

### `tests/<api>/<group>/<NN>_<category>.robot`
- `01_functional`: positive case, mỗi TC assertion riêng
- `02_edge_case`: boundary, file format → CSV nếu lặp pattern
- `02b_edge_flows`: cache, replay, vendor failure → Individual
- `03_business_rules`: formula validators, R1–Rn
- `04_field_validation`: header + param + body field → CSV chia nhóm
- `04b_*_flows`: cần multipart raw, 2 file, custom Content-Type
- `05_security`: XSS, SQLi, IDOR, mass assignment, HTTPS, CORS

### File naming convention
- snake_case cho file
- Robot TC name: `<TC_ID> <Short description>` (vd `OCR_API-1 OCR CCCD VN`)
- Tag chuẩn: `high|medium|low` + `<api_group>` + `<category>` + `<spec_section>`

## SKIP marker

TC bị skip phải có lý do rõ ràng trong code:
```robot
TC_XXX [SKIP — TRADE-OFF #3]
    [Tags]    skip    trade-off
    Skip    Lý do cụ thể (vd: API key cố định nội bộ, không expire)
```

Tag `skip` để filter ra khi report coverage.
