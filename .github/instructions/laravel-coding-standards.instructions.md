---
name: laravel-coding-standards
description: "Use when: writing, reviewing, or refactoring Laravel PHP code, Vue/Inertia components, or tests. Apply Laravel best practices, PHP 8 modern syntax, domain-driven architecture, service layer pattern, proper validation, API resources, typed Vue components, authorization checks, error handling, and Pest testing conventions."
applyTo: "**/*.php,resources/js/**/*.vue,resources/js/**/*.ts,tests/**/*.php"
---

# Laravel Coding Standards

Best practices for this Laravel v13 project (PHP 8.3, Vue 3, Inertia v3, Pest v4).

## Quick Reference

| Category | Rule |
|----------|------|
| **PHP Syntax** | Constructor property promotion; explicit type hints; type returns |
| **Architecture** | Organize by feature domain; extract to services; thin controllers |
| **Validation** | Form Requests only; normalize in `prepareForValidation()`; localized messages |
| **Error Handling** | Try-catch in services; Inertia controllers use redirect+flash; API controllers use standard JSON format |
| **Authorization** | Every endpoint requires permission check via Spatie Laravel Permission |
| **Database** | Changes via migrations only; always include rollback (down method) |
| **N+1 Prevention** | Use `with()` eager loading for Eloquent relations, especially in list queries |
| **APIs** | Eloquent API Resources; conditional field hiding |
| **Vue** | `<script setup lang="ts">`; `defineProps<T>()`; Composition API only; named slots |
| **Testing** | Descriptive test names; factories; one feature per test; run only relevant tests |
| **Naming** | snake_case (database), camelCase (PHP variables), PascalCase (classes) |
| **Style** | Run Pint/Prettier before commit; PHPDoc; separator comments for sections |

---

## PHP Conventions

**Constructor Promotion**: Use PHP 8 syntax. Avoid empty `__construct()` methods.

```php
// ✅ Good
public function __construct(
    protected ProductService $service,
    protected StockService $stock,
) {}
```

**Type Hints & Returns**: All parameters and returns must be typed. Include array shapes in PHPDoc.

```php
/** @param array{keyword?: string, limit?: int} $filters
 *  @return array<int, ProductData> */
public function search(array $filters = []): array
```

**Match Over Switch**: Use `match()` for type/status comparisons.

```php
public function getStatusLabel(): string {
    return match($this->status) {
        Status::ACTIVE => 'Aktif',
        Status::INACTIVE => 'Tidak Aktif',
        default => 'Tidak Dikenal',
    };
}
```

**Builder Scopes**: Encapsulate query logic in model scopes. Never duplicate WHERE clauses.

```php
public function scopeActive(Builder $query): Builder {
    return $query->where('status', Status::ACTIVE);
}

// Usage: Product::active()->search($keyword)->get();
```

**PHPDoc Over Comments**: Use PHPDoc blocks for methods/properties; reserve inline comments for non-obvious logic only.

## Architecture Patterns

**Domain-Driven Organization**: Organize by feature domain, not technical layer (Products/, Kasir/, Suppliers/, etc.). Each domain contains controllers, models, services, and requests.

**Service Layer**: All business logic lives in services. Controllers route requests; services handle rules, state changes, and calculations.

```php
// ✅ Thin controller
public function store(StockOutRequest $request, StockOutService $service): RedirectResponse {
    $stockOut = $service->create($request->validated());
    return redirect()->route('stock-outs.show', $stockOut);
}

// ✅ Logic in service
public function create(array $data): StockOut {
    $stockOut = $this->model->create($data);
    foreach ($data['items'] as $item) {
        $this->stock->decrease($item['product_id'], $item['quantity'], $data['warehouse_id']);
    }
    return $stockOut;
}
```

## Validation & Form Requests

**Use Form Requests**: Always use Form Request classes; never validate inline in controllers.

**Normalize Data**: Use `prepareForValidation()` to trim whitespace, convert empty strings to null, and standardize input before validation.

```php
protected function prepareForValidation(): void {
    $this->merge([
        'name' => trim($this->name),
        'sku' => trim($this->sku) ?: null,
    ]);
}
```

**Localized Messages**: All error messages must be in Indonesian. Define in `messages()` method.

```php
public function messages(): array {
    return [
        'name.required' => 'Nama produk wajib diisi',
        'sku.unique' => 'SKU sudah digunakan',
    ];
}
```

## Error Handling

> ⚠️ **Response style depends on route type — do not mix them.**

### Inertia Controllers (`routes/web.php`) — pages rendered to the browser

Do **not** manually return `response()->json([...])` from these controllers.

- **Validation failure** → let `ValidationException` propagate; Inertia automatically
  redirects back with errors available via shared props (`$page.props.errors`).
- **Success** → `return redirect()->route('products.index')->with('success', 'Produk berhasil disimpan');`
- **Business-logic failure** (e.g. duplicate SKU, insufficient stock) →
  `return redirect()->back()->with('error', 'Stok tidak mencukupi');`

```php
// ✅ Inertia controller — success/failure via redirect + flash
public function store(StockOutRequest $request, StockOutService $service): RedirectResponse {
    try {
        $service->create($request->validated());
        return redirect()->route('stock-outs.index')->with('success', 'Stok keluar berhasil dicatat');
    } catch (InsufficientStockException $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
}
```

### API Controllers (`routes/api.php`, e.g. `/v1/...`) — pure REST endpoints

Use the **Standard JSON Response Format** (see below) for every response, success or error.

**Try-Catch in Services**: All service methods should wrap risky operations in try-catch blocks, regardless of whether they're called from Inertia or API controllers. Only the *controller* decides how the outcome is surfaced (redirect vs JSON).

**Never expose stack traces to users**, in either style.

```php
// ✅ API controller — JSON format
try {
    $stockOut = $service->create($data);
} catch (DuplicateUnitException $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null,
        'errors' => null,
    ], 422);
}
```

## Authorization

**Every Endpoint Requires Permission Check**: Use Spatie Laravel Permission middleware or `$this->authorize()` in every controller method. Never skip authorization, even for quick testing.

```php
// ✅ Via middleware
Route::post('/products', [ProductController::class, 'store'])
    ->middleware('permission:create-products');

// ✅ Via policy
$this->authorize('create', Product::class);

// ✅ Via middleware in controller
public function __construct() {
    $this->middleware('permission:view-products')->only('index', 'show');
}
```

**Missing Permissions**: If a permission doesn't exist in the database/seeder, **ask the user before creating it**. Do not auto-create permissions.

## N+1 Prevention

**Always Use Eager Loading**: When querying relations in lists/index, use `with()` to prevent N+1 queries.

```php
// ✅ Good
$products = Product::with(['category', 'warehouse'])->paginate();

// ❌ Avoid (causes N+1)
$products = Product::paginate();
// Then in template: $product->category->name
```

## Database Migrations

**Changes via Migrations Only**: Never edit database directly. All schema changes must go through Laravel migrations.

**Always Include Rollback**: Every migration must have a complete `down()` method.

```php
public function up(): void {
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
}

public function down(): void {
    Schema::dropIfExists('products');
}
```

## API & Resources

**Eloquent API Resources**: Always use Eloquent API Resources to transform model data. Never return raw models.

```php
// ✅ Good
class ProductController {
    public function index(): AnonymousResourceCollection {
        return ProductResource::collection(Product::with('category')->paginate());
    }
}

// ✅ Resource hides sensitive data
class ProductResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost' => $this->when($request->user()?->isAdmin(), $this->cost),
        ];
    }
}
```

## Vue & Inertia Patterns

**Script Setup with TypeScript**: Use `<script setup lang="ts">` exclusively. No Options API. Define props via `defineProps<T>()` with strict typing.

```vue
<script setup lang="ts">
interface Props {
  title: string
  count?: number
}

withDefaults(defineProps<Props>(), {
  count: 0,
})

const emit = defineEmits<{
  close: []
  select: [id: number]
}>()
</script>
```

**Named Slots**: Use named slots for flexible content injection. Never hardcode layout.

```vue
<template>
  <div class="card">
    <slot name="header" />
    <slot />
    <slot name="footer" />
  </div>
</template>
```

**Composition API Only**: Use Composition API with `<script setup>`. No Options API or template directives like `v-model:property`.

**Component Organization**: Feature components in `resources/js/Components/<Feature>/`; types in `resources/js/types/`; icons via Lucide Vue Next or Heroicons; styling via Tailwind v4.

---

## Testing (Pest v4)

**Test Structure**: Write descriptive test names focused on business outcomes. Use `beforeEach()` for setup and factories for fixtures.

```php
describe('StockOutService', function () {
    beforeEach(function () {
        $this->service = app(StockOutService::class);
    });

    it('decreases_product_stock_when_stock_out_is_stored', function () {
        $product = Product::factory()->create(['stock' => 100]);
        $this->service->create(['product_id' => $product->id, 'quantity' => 20]);
        expect($product->refresh()->stock)->toBe(80);
    });
});
```

**RefreshDatabase & Factories**: Use `RefreshDatabase` trait and factories. Never hardcode test data.

```php
uses(RefreshDatabase::class);

it('filters products', function () {
    Product::factory()->create(['name' => 'Laptop']);
    Product::factory()->create(['name' => 'Mouse']);
    $results = Product::search('Laptop')->get();
    expect($results)->toHaveCount(1);
});
```

**Testing Efficiency**: Run only relevant tests for your change. Use `php artisan test --filter=testName` or `php artisan test path/to/test.php`.

**Debug Attempt Limit**: See "Test Debug Attempts" under Developer Workflow & Constraints below for the precise definition of an "attempt" and what happens after the limit is reached.

## Code Style & Formatting

**Run Pint**: All PHP files must pass Laravel Pint. Run `vendor/bin/pint --dirty` before committing.

**Run Prettier & ESLint**: All Vue/TypeScript files must pass ESLint and Prettier. Run `npm run lint` and `npm run format` before committing.

**Separator Comments**: Use repeating dashes to divide controller methods by feature/specification.

```php
class ProductController {
    // -------------------------
    // SF-01 — GET /v1/products
    // -------------------------
    public function index(ProductRequest $request): AnonymousResourceCollection {
        return ProductResource::collection(Product::search($request->query('keyword'))->paginate());
    }

    // -------------------------
    // SF-02 — POST /v1/products
    // -------------------------
    public function store(ProductRequest $request, ProductService $service): ProductResource {
        return ProductResource::make($service->create($request->validated()));
    }
}
```

## Naming Conventions

**Database**: `snake_case` (products, stock_items, warehouse_codes)
**PHP Variables & Methods**: `camelCase` (productName, getStockCount())
**PHP Classes**: `PascalCase` (ProductService, StockController)
**Vue Variables & Functions**: `camelCase` (isLoading, handleSubmit)
**Vue Components**: `PascalCase` (ProductForm, StatCard)
**Constants**: `UPPER_SNAKE_CASE` (MAX_STOCK_ITEMS, DEFAULT_WAREHOUSE_ID)

---

## Developer Workflow & Constraints

### Standard JSON Response Format (API controllers only)

Applies **only** to `routes/api.php` endpoints. Inertia controllers use redirect + flash (see "Error Handling" above) and must never return this shape.

Error response:

```json
{
  "success": false,
  "message": "Deskripsi error dalam bahasa Indonesia",
  "data": null,
  "errors": {
    "field_name": ["Error message per field"],
    "other_field": ["Multiple errors allowed"]
  }
}
```

Success response:

```json
{
  "success": true,
  "message": "Operasi berhasil",
  "data": { /* response payload */ },
  "errors": null
}
```

### Changes Per Iteration — Based on Logical Unit of Work, Not File Count

Do not stop mid-way through a single logical unit of work just because a file count is reached. A "logical unit of work" is one complete feature or fix — for example, a full CRUD feature (migration + model + service + form request + controller + resource + test) counts as **one** iteration, even if it touches more than 5 files.

- Finish the full logical unit before pausing.
- Confirm with the user **after** a logical unit is complete — not based on a file-count threshold.
- If a single user request spans **multiple** features/domains at once (e.g. "buat modul Produk dan modul Supplier"), pause and confirm with the user **between** each feature, not mid-feature.
- Do not chain multiple unrelated features into one iteration without checking in between them.

### Test Debug Attempts — Definition and Reset Rule

**Definition of one "attempt"**: one test run performed *after* making at least one code change intended to fix the failure. Re-running the same test without any intervening code change does **not** count as a new attempt — it's still part of the same attempt, and repeating it without analysis is not allowed regardless.

**Limit**: maximum **2 attempts** to fix a failing test.

If still failing after 2 attempts:
1. Stop trying to fix it automatically.
2. Report to the user: the error message, the test name, what was tried in each attempt, and the suspected root cause.
3. Wait for user confirmation before continuing.

**After user approves continuing (attempt 3+)**:
- The attempt counter resets to 0 for that specific issue.
- Before executing attempt 3, explain the new strategy to the user first — do not immediately re-run automatically.

### Authorization & Permissions

**Missing Permissions**: If a permission doesn't exist in the database, ask the user before creating it. Do not auto-create permissions.

### Package Installation

**New Dependencies**: Always ask the user before installing new packages. Provide reasoning and proposed version if requested.

### Confirmation Checkpoints — Priority Rule

When completing a full logical unit of work (see above) would otherwise conflict with frequent check-ins, **prioritize finishing the logical unit** over interrupting the user repeatedly. Only pause to confirm at these points:

1. A logical unit of work is complete.
2. 2 debug attempts on the same test have failed.
3. A new package/dependency needs to be installed.
4. A new permission needs to be created.

Do not add confirmation checkpoints outside these four conditions.

---

## Common Patterns to Avoid

❌ **Hardcoded URLs** — Use `route()` function or Wayfinder route imports
❌ **Raw model responses** — Always use API Resources
❌ **Inline SQL** — Use Eloquent scopes and builders
❌ **Fat controllers** — Extract logic to services
❌ **Untyped Vue components** — Always use TypeScript and `defineProps<T>()`
❌ **Untested features** — Write tests before committing
❌ **Mixed concerns in models** — Keep models lean; use services for business logic
❌ **Unlocalized error messages** — Provide Indonesian messages in form request methods
❌ **JSON responses from Inertia controllers** — Use redirect + flash instead; JSON format is API-only
❌ **Stopping mid-feature due to file count** — Finish the logical unit of work first

---

## References

- [Laravel v13 Documentation](https://laravel.com/docs/13.x)
- [Inertia v3 Documentation](https://inertiajs.com)
- [Pest v4 Documentation](https://pestphp.com)
- [Tailwind v4 Documentation](https://tailwindcss.com)
- [Vue 3 Documentation](https://vuejs.org)
- [Laravel Pint](https://github.com/laravel/pint)