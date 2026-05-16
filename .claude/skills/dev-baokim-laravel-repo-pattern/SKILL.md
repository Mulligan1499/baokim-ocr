---
name: dev-baokim-laravel-repo-pattern
description: |
  Skill design và review code Laravel theo pattern Repository/Service/Controller chuẩn Baokim (BKM03). Áp dụng đầy đủ rule BKM01-08 khi sinh code: KHÔNG dùng Foreign Key (BKM01), CẤM DB::table chỉ dùng Eloquent (BKM02), tách 3 layer rõ ràng (BKM03), KHÔNG query trong loop (BKM04), ID sizing đúng INT vs BIGINT (BKM05), log tables phải partition (BKM06), hash search dùng DB index (BKM07), fintech security priorities (BKM08). Đầu vào: business logic description hoặc existing code snippet. Đầu ra: Repository class skeleton + Service class skeleton + Controller skeleton + sample test + pre-merge checklist, hoặc review report với violations theo rule ID cụ thể (BKMxx, SCRxx, IDRxx).
  Triggers: "code Laravel cho [feature]", "Repository Baokim cho [model]", "thiết kế Service cho [feature]", "review Controller này", "tách logic ra Repository", "viết Repository pattern cho [model]", "thiết kế feature theo BKM03", "review code có vi phạm BKM không".
  Anti-triggers (KHÔNG dùng skill này khi): non-Laravel projects (Symfony, Phalcon, Yii), pure SQL design không có code Laravel (dùng dev-baokim-db-schema-designer), generic Laravel best practice không liên quan pattern Baokim (dùng web search/docs), code review về performance optimization không liên quan pattern (dùng dev-baokim-sql-reviewer).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: dev
created: 2026-05-15
updated: 2026-05-15
tags: [dev, laravel, baokim, repository-pattern, service-pattern, code-architecture, baokim-standards]
---

# Baokim Laravel Repository/Service/Controller Pattern

## Mục đích

Skill encode tacit knowledge của Baokim về cách viết code Laravel đúng pattern, giúp dev:
- Sinh code skeleton theo 3-layer architecture nhất quán
- Không vi phạm BKM01-08 ngay từ đầu (tránh rework PR review)
- Review code có sẵn, identify violations theo rule ID

**Reference foundation**: `05-baokim-db-standard.md` (BKM01-08 + SCR + IDR + SQR rules)

## Khi nào dùng skill này

✅ Dev cần code feature mới có DB interaction
✅ Dev refactor Controller "fat" thành Service + Repository
✅ Dev review PR có code Laravel
✅ Dev cần adapt pattern cho dự án OCR (Service Stage 1-7 harness)
✅ Onboarding dev mới — show pattern Baokim qua examples
✅ Migrate code legacy (DB::table → Eloquent)

❌ Pure SQL schema design — dùng `dev-baokim-db-schema-designer`
❌ Query performance tuning — dùng `dev-baokim-sql-reviewer`
❌ Non-Laravel framework
❌ Generic Laravel best practice (dùng Laravel official docs)

## Workflow

### Step 1: Identify feature scope

Hỏi 3 thông tin (max, không hơn):

1. **Feature name + business purpose** (1-2 câu)
2. **Models involved** + access patterns (vd: "list by merchant + sort by date")
3. **Special concerns**: transaction critical? PII? high-volume?

### Step 2: Design 3 layers

Áp dụng BKM03 strictly:

**Repository layer** — 1 function = 1 query, KHÔNG logic
```
Pattern:
- Naming: <Model>Repository (vd: PackageRepository)
- Methods: describe query intent (findActiveByMerchantId, countCreatedToday)
- KHÔNG if/else logic chọn query
- KHÔNG validate, transform data
- CHỈ chứa func cho 1 model
```

**Service layer** — business logic + orchestration
```
Pattern:
- Naming: <Feature>Service (vd: PackageCreationService)
- Logic nghiệp vụ
- Validation referential (thay FK, theo BKM01)
- Transaction management
- Call nhiều Repository
- Inject Repository qua constructor (DI)
```

**Controller layer** — thin
```
Pattern:
- Naming: <Resource>Controller
- KHÔNG có logic
- Chỉ: validate request → call Service → return response
- KHÔNG gọi Repository trực tiếp
- Inject Service qua constructor
```

### Step 3: Apply BKM rules trong code

Checklist sinh code, mỗi class generate qua filter:

- [ ] BKM01: KHÔNG có `FOREIGN KEY` trong migration (validation referential trong Service)
- [ ] BKM02: CHỈ Eloquent Model, không `DB::table()`, không `DB::raw()` standalone
- [ ] BKM03: Tách 3 layer, Controller không touch Repository
- [ ] BKM04: KHÔNG query trong for/foreach/while — eager load với `with()` hoặc batch
- [ ] BKM05: ID sizing đúng theo expected volume (INT vs BIGINT)
- [ ] BKM06: Log tables → migration có partition by RANGE(TO_DAYS(created))
- [ ] BKM07: Hash search dùng DB index, không hash trong PHP rồi WHERE =
- [ ] BKM08: PII column flag cho encryption + audit + soft delete

### Step 4: Output deliverables

4 files generate:

1. `app/Repositories/{Model}Repository.php`
2. `app/Services/{Feature}Service.php`
3. `app/Http/Controllers/{Resource}Controller.php`
4. `tests/Feature/{Feature}Test.php` (sample test)

Plus: pre-merge checklist với rule ID compliance.

## Templates

### Repository skeleton

```php
<?php

namespace App\Repositories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class PackageRepository
{
    /**
     * Tìm packages active của 1 merchant.
     * 1 query, KHÔNG logic chọn query (BKM03).
     */
    public function findActiveByMerchantId(int $merchantId): Collection
    {
        return Package::where('merchant_id', $merchantId)
                      ->where('status', Package::STATUS_ACTIVE)
                      ->orderByDesc('created')
                      ->get();
    }

    /**
     * Đếm packages tạo trong ngày.
     */
    public function countCreatedToday(): int
    {
        return Package::whereDate('created', today())->count();
    }

    /**
     * Batch find theo IDs — KHÔNG dùng trong loop (BKM04 violation prevention).
     */
    public function findByIds(array $ids): Collection
    {
        // Limit 500 params theo SQR02.2
        if (count($ids) > 500) {
            throw new \InvalidArgumentException('IDs count exceeds 500');
        }
        return Package::whereIn('id', $ids)->get();
    }
}
```

### Service skeleton

```php
<?php

namespace App\Services;

use App\Repositories\PackageRepository;
use App\Repositories\MerchantRepository;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Exceptions\BusinessException;

class PackageCreationService
{
    public function __construct(
        private PackageRepository $packageRepo,
        private MerchantRepository $merchantRepo,
    ) {}

    /**
     * Tạo package mới với validation referential (thay FK theo BKM01).
     */
    public function create(int $merchantId, array $data): Package
    {
        // Validation referential (BKM01: no FK ở DB → check ở Service)
        $merchant = $this->merchantRepo->findById($merchantId);
        if (!$merchant) {
            throw new BusinessException('MERCHANT_NOT_FOUND', 'Merchant không tồn tại');
        }
        if (!$merchant->is_active) {
            throw new BusinessException('MERCHANT_INACTIVE', 'Merchant đang inactive');
        }

        // Transaction (BKM03 Service layer manages transaction)
        return DB::transaction(function () use ($merchantId, $data) {
            $package = Package::create([
                'merchant_id' => $merchantId,
                'name' => $data['name'],
                'status' => Package::STATUS_ACTIVE,
                // ... other fields
            ]);

            // Audit log nếu sensitive (BKM08)
            // AuditLogService::record('package.created', ['package_id' => $package->id]);

            return $package;
        });
    }
}
```

### Controller skeleton

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePackageRequest;
use App\Services\PackageCreationService;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(
        private PackageCreationService $packageService,
    ) {}

    /**
     * Controller THIN: validate → call Service → return response.
     * KHÔNG logic ở đây (BKM03).
     */
    public function store(CreatePackageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $package = $this->packageService->create(
            merchantId: $validated['merchant_id'],
            data: $validated,
        );

        return response()->json([
            'data' => $package,
            'request_id' => $request->header('X-Request-ID'),
        ], 201);
    }
}
```

### Sample test skeleton

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Merchant;
use App\Services\PackageCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PackageCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_package_success(): void
    {
        $merchant = Merchant::factory()->active()->create();
        
        $response = $this->postJson('/api/v1/packages', [
            'merchant_id' => $merchant->id,
            'name' => 'Test Package',
        ]);

        $response->assertCreated()
                 ->assertJsonStructure(['data' => ['id', 'name'], 'request_id']);
    }

    public function test_create_package_fails_when_merchant_inactive(): void
    {
        $merchant = Merchant::factory()->inactive()->create();
        
        $response = $this->postJson('/api/v1/packages', [
            'merchant_id' => $merchant->id,
            'name' => 'Test Package',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'MERCHANT_INACTIVE');
    }
}
```

## Examples

### Example 1: OCR feature (current project)

**Input**: "Code Laravel cho POST /api/v1/ocr/extract — upload file, gọi harness 7 stages, lưu kết quả"

**Skill output** (high-level structure):

```
app/Http/Controllers/OcrController.php
  - store(UploadOcrRequest $request): chỉ validate + call OcrPipelineService

app/Services/OcrPipelineService.php
  - extract(UploadedFile $file, int $userId): orchestrate 7 stages
  - private callStage1Classifier(), callStage2Extractor(), ... (composable)
  - Transaction wrapper for Stage 6 persist

app/Services/Ocr/Stage1ClassifierService.php
app/Services/Ocr/Stage2VisionExtractorService.php  
  - Wrap Anthropic API call
  - Reference skill cross-llm-extraction-prompt cho prompt design
app/Services/Ocr/Stage3ValidatorService.php
app/Services/Ocr/Stage4PiiMaskerService.php
  - Reference skill vn-pii-masker
app/Services/Ocr/Stage5ConfidenceAggregatorService.php

app/Repositories/OcrDocumentRepository.php
  - createDocument(array $data): Document
  - findByRequestId(string $uuid): ?Document
  - listByUserId(int $userId, array $filters, int $limit, ?string $cursor): array

app/Repositories/OcrAuditLogRepository.php  ← BKM06 partition
  - record(string $event, array $context): void
  
app/Repositories/OcrVendorCallRepository.php  ← BKM06 partition
  - logCall(array $callData): void
```

**Pre-merge checklist** generated:
- [x] BKM01: 3 bảng không FK
- [x] BKM02: Eloquent only trong tất cả Repository
- [x] BKM03: 3 layer rõ, Controller thin, Service orchestrate, Repository query only
- [x] BKM04: Không query trong loop (Stage 1-5 sequential, không parallel loop)
- [x] BKM05: ocr_documents INT (~50k rows/year). ocr_audit_logs + ocr_vendor_calls BIGINT (partition)
- [x] BKM06: 2 bảng audit + vendor_calls partition RANGE monthly
- [ ] BKM07: N/A (không có hash search trong skill này)
- [x] BKM08: PII handle qua Stage 4. Encryption at rest S3 + DB column.

### Example 2: Payment domain (generic Baokim feature)

**Input**: "Service tạo payment transaction cho merchant, có validate signature, callback URL"

**Skill output** structure:

```
app/Http/Controllers/PaymentController.php
  - create(CreatePaymentRequest): thin
  - callback(CallbackRequest): thin

app/Services/PaymentCreationService.php
  - create(int $merchantId, array $data): Payment
    + Validate merchant active (BKM01 replacement)
    + Validate signature
    + Generate tx_code unique
    + DB::transaction: insert payment + log
    + Return Payment model

app/Services/PaymentCallbackService.php
  - handle(string $txCode, array $callbackData): void
    + Find payment by tx_code
    + Validate state transition
    + Update + audit log
    + Trigger merchant webhook (qua queue)

app/Repositories/PaymentRepository.php
  - findByTxCode(string $code): ?Payment
  - findActiveByMerchant(int $merchantId): Collection
  - updateStatus(int $id, int $newStatus): bool

app/Repositories/PaymentLogRepository.php  ← partition (BKM06)
```

### Example 3: Review existing Controller (sai phổ biến)

**Input** (user paste):
```php
class TransactionController {
    public function index(Request $r) {
        $merchantId = $r->merchant_id;
        $list = DB::table('transactions')
                  ->where('merchant_id', $merchantId)
                  ->where('status', 1)
                  ->get();
        $result = [];
        foreach ($list as $tx) {
            $merchant = DB::table('merchants')->where('id', $tx->merchant_id)->first();
            $tx->merchant_name = $merchant->name;
            $result[] = $tx;
        }
        return response()->json($result);
    }
}
```

**Skill output** — Violations report:

| Rule ID | Severity | Issue | Fix |
|---|---|---|---|
| BKM02 | M | `DB::table('transactions')` — cấm | Dùng `Transaction::query()` Eloquent |
| BKM02 | M | `DB::table('merchants')` — cấm | Dùng `Merchant` model |
| BKM03 | M | Controller chứa query + logic | Tách sang Service + Repository |
| BKM04 | M | Query trong foreach — N+1 problem | Eager load `Transaction::with('merchant')->...` |
| SCR04.2 | R | `where('status', 1)` magic number | `where('status', Transaction::STATUS_ACTIVE)` constant |

**Refactor proposal**:

```php
// Controller (thin)
public function index(ListTransactionRequest $request, TransactionListService $service): JsonResponse
{
    $list = $service->listByMerchant($request->validated()['merchant_id']);
    return TransactionResource::collection($list)->response();
}

// Service
class TransactionListService {
    public function __construct(private TransactionRepository $repo) {}
    
    public function listByMerchant(int $merchantId): Collection {
        return $this->repo->findActiveByMerchantWithMerchant($merchantId);
    }
}

// Repository
class TransactionRepository {
    public function findActiveByMerchantWithMerchant(int $merchantId): Collection {
        return Transaction::with('merchant:id,name')  // eager load, 2 queries total
                          ->where('merchant_id', $merchantId)
                          ->where('status', Transaction::STATUS_ACTIVE)
                          ->get();
    }
}
```

→ Từ N+1 queries thành 2 queries. Code tách 3 layer rõ.

## What NOT to do

❌ KHÔNG sinh code có `FOREIGN KEY` trong migration (BKM01)
❌ KHÔNG sinh `DB::table()` standalone — chỉ `DB::raw()` BÊN TRONG Eloquent scope (BKM02)
❌ KHÔNG đặt logic trong Repository — Repository chỉ chứa 1 query/method (BKM03)
❌ KHÔNG để Controller gọi trực tiếp Repository — phải qua Service (BKM03)
❌ KHÔNG sinh code có query trong for/foreach (BKM04)
❌ KHÔNG dùng FLOAT cho tiền — DECIMAL (SCR02.5)
❌ KHÔNG dùng INT timestamp — DATETIME (SCR02.4)
❌ KHÔNG hardcode magic number trong WHERE — dùng constant (vd: `Model::STATUS_ACTIVE`)
❌ KHÔNG bỏ qua transaction cho multi-table write
❌ KHÔNG bỏ qua audit log cho PII/financial actions (BKM08)

## Pre-merge checklist template

Cuối mỗi PR có code Laravel, dev tick checklist:

```markdown
## BKM Compliance
- [ ] BKM01: Migration KHÔNG có FOREIGN KEY constraint
- [ ] BKM02: Eloquent only, KHÔNG DB::table standalone
- [ ] BKM03: 3 layer tách rõ (Controller thin, Service logic, Repository query)
- [ ] BKM04: KHÔNG query trong loop, dùng eager load `with()` hoặc batch
- [ ] BKM05: ID type đúng volume (INT vs BIGINT theo expected rows)
- [ ] BKM06: Log tables có partition migration
- [ ] BKM07: Hash search dùng DB index, không hash-then-equal
- [ ] BKM08: PII columns flag for encryption + audit

## Code style
- [ ] Method name describe intent (findActiveByMerchantId, không getList1)
- [ ] Constants thay magic number (STATUS_ACTIVE, không = 1)
- [ ] Type hints + return types trên tất cả methods
- [ ] DocBlock cho complex method
- [ ] Test coverage: happy path + ≥1 edge case
```

## Maintenance

- Update khi: leadership Baokim thay đổi BKM rules
- Update khi: Laravel release breaking change pattern (vd: Laravel 12 collection breaking change)
- Update khi: phát hiện new anti-pattern hay gặp trong code base
- Version bump: minor cho add rule/example, major cho breaking change template
- Test prompts: xem `tests/prompts.md`

## Roadmap

- v0.2.0: tách `references/code-templates/` cho per-domain templates (payment, auth, ocr...)
- v0.3.0: integration với phpstan/larastan custom rules
- v1.0.0 (active): production-tested across 5+ features
