<?php

use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────

function srSeedRoles(): void
{
    foreach (['owner', 'kasir', 'manajer', 'petugas_gudang'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function srMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function srMakeUserWithRole(string $roleName, Store $store): User
{
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function srMakeProduct(Store $store, ?Category $category = null): Product
{
    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $cat = $category ?? Category::firstOrCreate(
        ['name' => 'Kategori Laporan'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    return Product::create([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Laporan '.uniqid(),
        'sku' => 'LAP-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 10000,
        'buy_price' => 7000,
        'initial_stock' => 50,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
}

function srMakeSession(Store $store, User $user): PosSession
{
    return PosSession::create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'device_id' => 'TEST-DEVICE-SR',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);
}

function srMakeTransaction(array $attrs): PosTransaction
{
    // session_id wajib ada — buat PosSession dummy jika tidak disupply
    $sessionId = $attrs['session_id'] ?? null;

    if (! $sessionId) {
        /** @var Store $store */
        $store = Store::find($attrs['store_id']);
        /** @var User $user */
        $user = User::find($attrs['user_id']);
        $sessionId = srMakeSession($store, $user)->id;
    }

    return PosTransaction::create([
        'transaction_number' => 'TRX-'.uniqid(),
        'idempotency_key' => (string) Str::uuid(),
        'store_id' => $attrs['store_id'],
        'session_id' => $sessionId,
        'cart_id' => $attrs['cart_id'] ?? null,
        'user_id' => $attrs['user_id'],
        'status' => $attrs['status'] ?? 'completed',
        'subtotal' => $attrs['subtotal'] ?? ($attrs['grand_total'] ?? 50000),
        'discount_total' => $attrs['discount_total'] ?? 0,
        'tax_total' => $attrs['tax_total'] ?? 0,
        'grand_total' => $attrs['grand_total'] ?? 50000,
        'paid_amount' => $attrs['paid_amount'] ?? ($attrs['grand_total'] ?? 50000),
        'change_amount' => $attrs['change_amount'] ?? 0,
        'source' => $attrs['source'] ?? 'online',
        'notes' => $attrs['notes'] ?? null,
        'transaction_at' => $attrs['transaction_at'] ?? now()->toDateTimeString(),
    ]);
}

// ──────────────────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────────────────

beforeEach(function () {
    srSeedRoles();
});

// ──────────────────────────────────────────────────────────
// 1. Akses berdasarkan role
// ──────────────────────────────────────────────────────────

it('owner dapat akses laporan penjualan', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales')->assertOk()->assertJsonPath('success', true);
});

it('manajer dapat akses laporan penjualan', function () {
    ['store' => $store] = srMakeOwnerWithStore();
    $manajer = srMakeUserWithRole('manajer', $store);
    Sanctum::actingAs($manajer);

    $this->getJson('/api/v1/reports/sales')->assertOk()->assertJsonPath('success', true);
});

it('kasir ditolak akses laporan penjualan', function () {
    ['store' => $store] = srMakeOwnerWithStore();
    $kasir = srMakeUserWithRole('kasir', $store);
    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/reports/sales')->assertStatus(403);
});

it('petugas_gudang ditolak akses laporan penjualan', function () {
    ['store' => $store] = srMakeOwnerWithStore();
    $gudang = srMakeUserWithRole('petugas_gudang', $store);
    Sanctum::actingAs($gudang);

    $this->getJson('/api/v1/reports/sales')->assertStatus(403);
});

it('user tanpa profile store_id ditolak', function () {
    $role = Role::where('name', 'manajer')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    // Tidak ada UserProfile → getStoreId() = null

    Sanctum::actingAs($user->fresh(['role', 'profile']));

    $this->getJson('/api/v1/reports/sales')->assertStatus(403);
});

it('unauthenticated user ditolak', function () {
    $this->getJson('/api/v1/reports/sales')->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────
// 2. Endpoint menggunakan /api/v1
// ──────────────────────────────────────────────────────────

it('endpoint laporan penjualan memakai prefix api v1', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    // Path lama tidak aktif
    $this->getJson('/api/reports/sales')->assertNotFound();

    // Path baru berjalan
    $this->getJson('/api/v1/reports/sales')->assertOk();
});

// ──────────────────────────────────────────────────────────
// 3. Isolasi data store
// ──────────────────────────────────────────────────────────

it('data store lain tidak ikut terhitung di laporan', function () {
    ['user' => $owner, 'store' => $storeA] = srMakeOwnerWithStore();
    ['user' => $ownerB, 'store' => $storeB] = srMakeOwnerWithStore();

    srMakeTransaction(['store_id' => $storeA->id, 'user_id' => $owner->id, 'grand_total' => 100000]);
    srMakeTransaction(['store_id' => $storeB->id, 'user_id' => $ownerB->id, 'grand_total' => 999000]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/reports/sales');
    $response->assertOk();

    expect((float) $response->json('data.summary.gross_sales'))->toBe(100000.0);
});

// ──────────────────────────────────────────────────────────
// 4. Status transaksi
// ──────────────────────────────────────────────────────────

it('status completed masuk ke gross_sales dan net_sales', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();

    srMakeTransaction([
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'grand_total' => 50000,
        'discount_total' => 0,
        'tax_total' => 0,
    ]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/reports/sales');
    $response->assertOk();

    $summary = $response->json('data.summary');
    expect((int) $summary['transaction_count'])->toBe(1);
    expect((float) $summary['gross_sales'])->toBe(50000.0);
    expect((float) $summary['net_sales'])->toBeGreaterThan(0.0);
});

it('status voided tidak masuk net_sales', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();

    srMakeTransaction([
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'status' => 'voided',
        'grand_total' => 50000,
    ]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/reports/sales');
    $response->assertOk();

    $summary = $response->json('data.summary');
    expect((int) $summary['transaction_count'])->toBe(0);
    expect((float) $summary['net_sales'])->toBe(0.0);
});

it('status voided masuk refund_or_void_total', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();

    srMakeTransaction([
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'status' => 'voided',
        'grand_total' => 75000,
    ]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/reports/sales');
    $response->assertOk();

    expect((float) $response->json('data.summary.refund_or_void_total'))->toBe(75000.0);
});

// ──────────────────────────────────────────────────────────
// 5. Validasi filter tanggal
// ──────────────────────────────────────────────────────────

it('date_start lebih besar dari date_end menghasilkan 422', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales?date_start=2026-08-31&date_end=2026-08-01')
        ->assertStatus(422);
});

it('filter tanggal valid tidak menghasilkan error', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales?date_start=2026-08-01&date_end=2026-08-31')
        ->assertOk();
});

// ──────────────────────────────────────────────────────────
// 6. average_order_value = 0 ketika tidak ada transaksi
// ──────────────────────────────────────────────────────────

it('average_order_value adalah 0 ketika tidak ada transaksi', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/reports/sales');
    $response->assertOk();

    expect((float) $response->json('data.summary.average_order_value'))->toBe(0.0);
});

// ──────────────────────────────────────────────────────────
// 7. Filter scoped ke store aktif
// ──────────────────────────────────────────────────────────

it('cashier_id dari store lain ditolak', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    ['store' => $storeB] = srMakeOwnerWithStore();
    $kasirB = srMakeUserWithRole('kasir', $storeB);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?cashier_id={$kasirB->id}")
        ->assertStatus(422);
});

it('cashier_id dari store aktif diterima', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();
    $kasir = srMakeUserWithRole('kasir', $store);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?cashier_id={$kasir->id}")
        ->assertOk();
});

it('product_id dari store lain ditolak', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    ['store' => $storeB] = srMakeOwnerWithStore();
    $productB = srMakeProduct($storeB);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?product_id={$productB->id}")
        ->assertStatus(422);
});

it('product_id dari store aktif diterima', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();
    $product = srMakeProduct($store);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?product_id={$product->id}")
        ->assertOk();
});

it('category_id dari store lain ditolak', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    ['store' => $storeB] = srMakeOwnerWithStore();

    $catB = Category::create([
        'name' => 'Kategori Store B',
        'status' => 'active',
        'created_by' => $storeB->owner_id,
        'updated_by' => $storeB->owner_id,
    ]);
    srMakeProduct($storeB, $catB);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?category_id={$catB->id}")
        ->assertStatus(422);
});

it('category_id yang punya produk di store aktif diterima', function () {
    ['user' => $owner, 'store' => $store] = srMakeOwnerWithStore();

    $cat = Category::create([
        'name' => 'Kategori Store A',
        'status' => 'active',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
    srMakeProduct($store, $cat);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/reports/sales?category_id={$cat->id}")
        ->assertOk();
});

// ──────────────────────────────────────────────────────────
// 8. Granularity validation
// ──────────────────────────────────────────────────────────

it('granularity tidak valid menghasilkan 422', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales?granularity=yearly')->assertStatus(422);
});

it('granularity valid day week month semua diterima', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    foreach (['day', 'week', 'month'] as $g) {
        $this->getJson("/api/v1/reports/sales?granularity={$g}")->assertOk();
    }
});

// ──────────────────────────────────────────────────────────
// 9. Filter options endpoint
// ──────────────────────────────────────────────────────────

it('owner dapat akses filter options', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/filter-options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['cashiers', 'products', 'categories', 'payment_methods']]);
});

it('kasir ditolak akses filter options', function () {
    ['store' => $store] = srMakeOwnerWithStore();
    $kasir = srMakeUserWithRole('kasir', $store);
    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/reports/filter-options')->assertStatus(403);
});

it('filter options hanya menampilkan kasir dari store aktif', function () {
    ['user' => $owner, 'store' => $storeA] = srMakeOwnerWithStore();
    ['store' => $storeB] = srMakeOwnerWithStore();

    $kasirA = srMakeUserWithRole('kasir', $storeA);
    $kasirB = srMakeUserWithRole('kasir', $storeB);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/reports/filter-options');
    $response->assertOk();

    $cashierIds = collect($response->json('data.cashiers'))->pluck('id')->toArray();

    expect($cashierIds)->toContain($kasirA->id);
    expect($cashierIds)->not->toContain($kasirB->id);
});

// ──────────────────────────────────────────────────────────
// 10. Top products endpoint
// ──────────────────────────────────────────────────────────

it('owner dapat akses top products', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales/top-products')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kasir ditolak akses top products', function () {
    ['store' => $store] = srMakeOwnerWithStore();
    $kasir = srMakeUserWithRole('kasir', $store);
    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/reports/sales/top-products')->assertStatus(403);
});

// ──────────────────────────────────────────────────────────
// 11. Struktur response
// ──────────────────────────────────────────────────────────

it('response laporan penjualan memiliki semua key yang diharapkan', function () {
    ['user' => $owner] = srMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/reports/sales')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'summary' => [
                    'transaction_count',
                    'gross_sales',
                    'net_sales',
                    'average_order_value',
                    'refund_or_void_total',
                ],
                'top_products',
                'payment_breakdown',
                'trends',
                'filters',
            ],
        ]);
});
