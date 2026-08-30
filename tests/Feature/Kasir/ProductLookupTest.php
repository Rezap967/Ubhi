<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\Kasir\PosTransactionItem;
use App\Models\Products\Brand;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Stock\StockBalance;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function setupStoreAndUser(string $roleName = 'kasir'): array
{
    foreach (['owner', 'kasir', 'petugas_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }

    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function createProduct(Store $store, array $attributes = [], int $stock = 100): Product
{
    $category = Category::firstOrCreate([
        'name' => 'Test Category',
        'status' => 'active',
    ], [
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);

    $brand = Brand::firstOrCreate([
        'name' => 'Test Brand',
        'status' => 'active',
    ], [
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);

    $unit = ProductUnit::firstOrCreate([
        'name' => 'Pcs',
        'status' => 'active',
    ], [
        'short_name' => 'pcs',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);

    $product = Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Default Test Product',
        'sku' => 'SKU-'.uniqid(),
        'barcode' => 'BC-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 5000,
        'buy_price' => 4000,
        'initial_stock' => $stock,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attributes));

    StockBalance::create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => $stock,
    ]);

    return $product;
}

// ──────────────────────────────────────────────
// Test 1-3: Role Access
// ──────────────────────────────────────────────

test('kasir bisa melihat daftar produk POS', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    createProduct($store, ['name' => 'Kopi Kapal Api']);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/products');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Kopi Kapal Api');
});

test('owner bisa melihat daftar produk POS', function () {
    ['user' => $owner, 'store' => $store] = setupStoreAndUser('owner');
    createProduct($store, ['name' => 'Teh Botol']);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/pos/products');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Teh Botol');
});

test('staff/gudang/manajer tidak bisa akses daftar produk POS', function () {
    ['user' => $staff, 'store' => $store] = setupStoreAndUser('petugas_gudang');

    Sanctum::actingAs($staff);
    $this->getJson('/api/v1/pos/products')->assertStatus(403);
});

// ──────────────────────────────────────────────
// Test 4-6: Product Filtering (Status, Sellable, Store)
// ──────────────────────────────────────────────

test('produk nonaktif tidak tampil', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    createProduct($store, ['name' => 'Active Product', 'status' => 'aktif']);
    createProduct($store, ['name' => 'Inactive Product', 'status' => 'nonaktif']);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/products');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('Active Product');
});

test('produk tidak sellable (tanpa harga) tidak tampil', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    createProduct($store, ['name' => 'Priced Product', 'sell_price' => 5000]);
    createProduct($store, ['name' => 'No Price Product', 'sell_price' => 0]);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/products');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('Priced Product');
});

test('produk dari store lain tidak tampil', function () {
    ['user' => $kasirA, 'store' => $storeA] = setupStoreAndUser('kasir');
    ['store' => $storeB] = setupStoreAndUser('owner'); // another store

    createProduct($storeA, ['name' => 'Product Store A']);
    createProduct($storeB, ['name' => 'Product Store B']);

    Sanctum::actingAs($kasirA);
    $response = $this->getJson('/api/v1/pos/products');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('Product Store A');
});

// ──────────────────────────────────────────────
// Test 7-10: Search and Filter Params
// ──────────────────────────────────────────────

test('search by name, sku, barcode berhasil', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    createProduct($store, ['name' => 'Aqua Botol', 'sku' => 'AQ-001', 'barcode' => '111']);
    createProduct($store, ['name' => 'Indomie Goreng', 'sku' => 'IND-01', 'barcode' => '222']);

    Sanctum::actingAs($kasir);

    // Search Name
    $this->getJson('/api/v1/pos/products?search=Botol')
        ->assertJsonPath('data.0.name', 'Aqua Botol')
        ->assertJsonCount(1, 'data');

    // Search SKU
    $this->getJson('/api/v1/pos/products?search=IND')
        ->assertJsonPath('data.0.name', 'Indomie Goreng')
        ->assertJsonCount(1, 'data');

    // Search Barcode exact via search field
    $this->getJson('/api/v1/pos/products?search=111')
        ->assertJsonPath('data.0.name', 'Aqua Botol')
        ->assertJsonCount(1, 'data');
});

test('filter category dan brand berhasil', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    $cat1 = Category::create(['name' => 'Cat1', 'status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]);
    $brand1 = Brand::create(['name' => 'Brand1', 'status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]);

    createProduct($store, ['name' => 'Prod A', 'category_id' => $cat1->id, 'brand_id' => $brand1->id]);
    createProduct($store, ['name' => 'Prod B']); // uses default test category

    Sanctum::actingAs($kasir);

    // Filter by Category
    $this->getJson("/api/v1/pos/products?category_id={$cat1->id}")
        ->assertJsonPath('data.0.name', 'Prod A')
        ->assertJsonCount(1, 'data');

    // Filter by Brand
    $this->getJson("/api/v1/pos/products?brand_id={$brand1->id}")
        ->assertJsonPath('data.0.name', 'Prod A')
        ->assertJsonCount(1, 'data');
});

// ──────────────────────────────────────────────
// Test 11-13: Resolve Barcode
// ──────────────────────────────────────────────

test('resolve barcode berhasil', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    createProduct($store, ['name' => 'Sabun Mandi', 'barcode' => '899123456789']);

    Sanctum::actingAs($kasir);

    $response = $this->getJson('/api/v1/pos/barcode/899123456789');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Sabun Mandi')
        ->assertJsonPath('data.barcode', '899123456789');
});

test('resolve barcode return 404 jika tidak ditemukan', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    createProduct($store, ['barcode' => '111']); // other product

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/pos/barcode/999') // not found
        ->assertStatus(404)
        ->assertJsonPath('message', 'Barcode tidak ditemukan.');
});

test('resolve barcode return 409 jika barcode duplikat', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    createProduct($store, ['name' => 'Prod 1', 'barcode' => 'DUPE-123']);
    createProduct($store, ['name' => 'Prod 2', 'barcode' => 'DUPE-123']); // Duplicate in same store

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/pos/barcode/DUPE-123')
        ->assertStatus(409)
        ->assertJsonPath('message', 'Barcode duplikat pada master data.');
});

// ──────────────────────────────────────────────
// Test 14-15: Stock Status
// ──────────────────────────────────────────────

test('produk stok status dikalkulasi benar', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    createProduct($store, ['name' => 'Kosong', 'barcode' => '000'], 0);
    createProduct($store, ['name' => 'Sedikit', 'barcode' => '111'], 3);
    createProduct($store, ['name' => 'Banyak', 'barcode' => '222'], 100);

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/pos/barcode/000')
        ->assertJsonPath('data.stock_status', 'out_of_stock')
        ->assertJsonPath('data.stock_available', 0);

    $this->getJson('/api/v1/pos/barcode/111')
        ->assertJsonPath('data.stock_status', 'low_stock')
        ->assertJsonPath('data.stock_available', 3);

    $this->getJson('/api/v1/pos/barcode/222')
        ->assertJsonPath('data.stock_status', 'available')
        ->assertJsonPath('data.stock_available', 100);
});

// ──────────────────────────────────────────────
// Test 16-17: Categories
// ──────────────────────────────────────────────

test('categories mengembalikan kategori dan product_count beserta opsi Semua', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    $catA = Category::create(['name' => 'Makanan', 'status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]);
    $catB = Category::create(['name' => 'Minuman', 'status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]);
    Category::create(['name' => 'Kosong', 'status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]); // No products

    // 2 products in Makanan, 1 in Minuman
    createProduct($store, ['category_id' => $catA->id]);
    createProduct($store, ['category_id' => $catA->id]);
    createProduct($store, ['category_id' => $catB->id]);

    Sanctum::actingAs($kasir);

    $response = $this->getJson('/api/v1/pos/categories?include_all=1');

    $response->assertStatus(200);
    $data = $response->json('data');

    // 1 "Semua" + 2 categories (Makanan, Minuman)
    expect(count($data))->toBe(3);

    expect($data[0]['name'])->toBe('Semua');
    expect($data[0]['product_count'])->toBe(3); // 2 + 1

    // Sort logic usually puts "Semua" first, then ordered by name or id
    // We check existence
    $names = array_column($data, 'name');
    expect(in_array('Makanan', $names))->toBeTrue();
    expect(in_array('Minuman', $names))->toBeTrue();
    expect(in_array('Kosong', $names))->toBeFalse(); // 0 products, shouldn't appear
});

// ──────────────────────────────────────────────
// Test 18-21: Top Selling
// ──────────────────────────────────────────────

test('top-selling mengembalikan empty array untuk fallback MVP', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');

    Sanctum::actingAs($kasir);

    $response = $this->getJson('/api/v1/pos/products/top-selling?period=today');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', []);
});

test('pagination meta tersedia pada produk list', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    createProduct($store, ['name' => 'Item 1']);

    Sanctum::actingAs($kasir);

    $response = $this->getJson('/api/v1/pos/products?per_page=15&page=1');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('meta.per_page'))->toBe(15);
    expect($response->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────
// Audit: Stok Berasal dari products.initial_stock (MVP)
// ──────────────────────────────────────────────

test('product lookup stock_available berasal dari products.initial_stock', function () {
    ['user' => $user, 'store' => $store] = setupStoreAndUser('owner');
    Sanctum::actingAs($user);

    // Produk punya initial_stock = 42
    createProduct($store, ['initial_stock' => 42]);

    $response = $this->getJson('/api/v1/pos/products');
    $response->assertOk();

    $product = $response->json('data.0');
    expect((float) $product['stock_available'])->toBe(42.0);
    expect($product['stock_status'])->toBe('available');
});

test('product lookup stock_available konsisten dengan cart add validation', function () {
    ['user' => $user, 'store' => $store] = setupStoreAndUser('owner');
    Sanctum::actingAs($user);

    $product = createProduct($store, ['sell_price' => 10000, 'initial_stock' => 50]);

    // Lookup menampilkan 50
    $lookupResponse = $this->getJson('/api/v1/pos/products');
    $lookupResponse->assertOk();
    expect((float) $lookupResponse->json('data.0.stock_available'))->toBe(50.0);

    // Add cart qty 1 harus berhasil (stok 50 cukup)
    PosSession::create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'device_id' => 'POS-LOOKUP-CONS',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => 'POS-LOOKUP-CONS',
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    // Stok di produk TIDAK boleh berkurang setelah add cart
    expect((int) $product->fresh()->initial_stock)->toBe(50);
});

test('product lookup mengembalikan stock_available terbaru setelah checkout sukses', function () {
    ['user' => $user, 'store' => $store] = setupStoreAndUser('owner');
    Sanctum::actingAs($user);

    $product = createProduct($store, ['sell_price' => 10000, 'initial_stock' => 10]);

    // Verify lookup awal = 10
    $initial = $this->getJson('/api/v1/pos/products');
    expect((float) $initial->json('data.0.stock_available'))->toBe(10.0);

    // Setup checkout
    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'device_id' => 'POS-LOOKUP-CHK',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    $cart = PosCart::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $user->id,
        'status' => 'active',
        'subtotal' => 20000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 20000,
    ]);

    PosCartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'unit_price' => 10000,
        'quantity' => 2,
        'discount_value' => 0,
        'subtotal' => 20000,
        'source' => 'manual',
    ]);

    // Checkout sukses
    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => 'POS-LOOKUP-CHK',
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 20000]],
    ])->assertStatus(201);

    // Setelah checkout, lookup harus mengembalikan stok 8 (10 - 2)
    $after = $this->getJson('/api/v1/pos/products');
    expect((float) $after->json('data.0.stock_available'))->toBe(8.0);
    expect($after->json('data.0.stock_status'))->toBe('available'); // 8 > 5
});

// ----------------------------------------------
// Test Best Selling Sort
// ----------------------------------------------

test('semua produk tampil dan default urut nama jika tidak ada sort', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    createProduct($store, ['name' => 'B Produk']);
    createProduct($store, ['name' => 'A Produk']);

    Sanctum::actingAs($kasir);
    $this->getJson('/api/v1/pos/products')
        ->assertJsonPath('data.0.name', 'A Produk')
        ->assertJsonPath('data.1.name', 'B Produk');
});

test('terlaris hari ini mengurutkan produk berdasarkan sales hari ini', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    $p1 = createProduct($store, ['name' => 'Produk Biasa']);
    $p2 = createProduct($store, ['name' => 'Produk Laris']);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    // Setup transaction today
    $tx = PosTransaction::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'completed',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'transaction_at' => now(),
        'transaction_number' => 'TRX-1',
        'idempotency_key' => 'IDEM-1',
        'receipt_number' => 'RCT-1',
    ]);
    PosTransactionItem::create([
        'transaction_id' => $tx->id,
        'product_id' => $p1->id,
        'quantity' => 1,
        'unit_price' => 5000,
        'subtotal' => 5000,
        'product_name' => $p1->name,
        'sku' => $p1->sku,
    ]);
    PosTransactionItem::create([
        'transaction_id' => $tx->id,
        'product_id' => $p2->id,
        'quantity' => 5,
        'unit_price' => 5000,
        'subtotal' => 25000,
        'product_name' => $p2->name,
        'sku' => $p2->sku,
    ]);

    Sanctum::actingAs($kasir);
    $this->getJson('/api/v1/pos/products?sort=best_selling&period=today')
        ->assertJsonPath('data.0.name', 'Produk Laris')
        ->assertJsonPath('data.1.name', 'Produk Biasa');
});

test('best selling hanya menghitung transaksi completed', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    $p1 = createProduct($store, ['name' => 'Produk A']);
    $p2 = createProduct($store, ['name' => 'Produk B']);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    // P1 terjual di transaksi cancelled
    $txVoid = PosTransaction::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'voided', // NOT completed
        'subtotal' => 10000,
        'grand_total' => 10000,
        'transaction_at' => now(),
        'transaction_number' => 'TRX-V',
        'idempotency_key' => 'IDEM-V',
        'receipt_number' => 'RCT-V',
    ]);
    PosTransactionItem::create([
        'transaction_id' => $txVoid->id,
        'product_id' => $p1->id,
        'quantity' => 10,
        'unit_price' => 5000,
        'subtotal' => 50000,
        'product_name' => $p1->name,
        'sku' => $p1->sku,
    ]);

    // P2 terjual 1 di transaksi completed
    $txCompleted = PosTransaction::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'completed',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'transaction_at' => now(),
        'transaction_number' => 'TRX-C',
        'idempotency_key' => 'IDEM-C',
        'receipt_number' => 'RCT-C',
    ]);
    PosTransactionItem::create([
        'transaction_id' => $txCompleted->id,
        'product_id' => $p2->id,
        'quantity' => 1,
        'unit_price' => 5000,
        'subtotal' => 5000,
        'product_name' => $p2->name,
        'sku' => $p2->sku,
    ]);

    Sanctum::actingAs($kasir);
    // P2 harus di atas karena P1 transaksinya void
    $this->getJson('/api/v1/pos/products?sort=best_selling&period=today')
        ->assertJsonPath('data.0.name', 'Produk B')
        ->assertJsonPath('data.1.name', 'Produk A');
});

test('terlaris minggu ini mengurutkan produk berdasarkan sales minggu ini', function () {
    ['user' => $kasir, 'store' => $store] = setupStoreAndUser('kasir');
    $p1 = createProduct($store, ['name' => 'Produk Kemarin']);
    $p2 = createProduct($store, ['name' => 'Produk Hari Ini']);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    // Setup transaction 2 days ago (this week)
    $tx = PosTransaction::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'completed',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'transaction_at' => now()->subDays(2),
        'transaction_number' => 'TRX-2',
        'idempotency_key' => 'IDEM-2',
        'receipt_number' => 'RCT-2',
    ]);
    PosTransactionItem::create([
        'transaction_id' => $tx->id,
        'product_id' => $p1->id,
        'quantity' => 10,
        'unit_price' => 5000,
        'subtotal' => 50000,
        'product_name' => $p1->name,
        'sku' => $p1->sku,
    ]);

    $tx2 = PosTransaction::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV1',
        'status' => 'completed',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'transaction_at' => now(),
        'transaction_number' => 'TRX-3',
        'idempotency_key' => 'IDEM-3',
        'receipt_number' => 'RCT-3',
    ]);
    PosTransactionItem::create([
        'transaction_id' => $tx2->id,
        'product_id' => $p2->id,
        'quantity' => 2,
        'unit_price' => 5000,
        'subtotal' => 10000,
        'product_name' => $p2->name,
        'sku' => $p2->sku,
    ]);

    Sanctum::actingAs($kasir);

    // Sort week (should include 2 days ago)
    // P1 (qty 10) > P2 (qty 2)
    $this->getJson('/api/v1/pos/products?sort=best_selling&period=week')
        ->assertJsonPath('data.0.name', 'Produk Kemarin');

    // Sort today (should NOT include 2 days ago)
    // P2 (qty 2) > P1 (qty 0)
    $this->getJson('/api/v1/pos/products?sort=best_selling&period=today')
        ->assertJsonPath('data.0.name', 'Produk Hari Ini');
});
