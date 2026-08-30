<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
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

const CART_DEVICE = 'POS-CART-TEST-001';

function cartSeedRoles(): void
{
    foreach (['owner', 'kasir', 'petugas_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function cartMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function cartMakeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function cartMakeUserWithRole(string $roleName, Store $store): User
{
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function cartOpenSession(User $user, Store $store, string $deviceId = CART_DEVICE): PosSession
{
    return PosSession::create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'device_id' => $deviceId,
        'status' => 'open',
        'opening_cash' => 500000,
        'opened_at' => now(),
    ]);
}

function cartMakeProduct(Store $store, array $attrs = [], int $stock = 50): Product
{
    $cat = Category::firstOrCreate(
        ['name' => 'Kategori Test'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $product = Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Test',
        'sku' => 'TST-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 5000,
        'buy_price' => 4000,
        'initial_stock' => $stock,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attrs));

    StockBalance::create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => $stock,
    ]);

    return $product;
}

// ──────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────

beforeEach(function () {
    cartSeedRoles();
});

// ──────────────────────────────────────────────
// Test 1-3: Melihat Cart
// ──────────────────────────────────────────────

test('kasir bisa melihat cart aktif jika session open', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    $session = cartOpenSession($kasir, $store);

    PosCart::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'status' => 'active',
        'subtotal' => 0, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 0,
    ]);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/cart?device_id='.CART_DEVICE);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['cart_id', 'session_id', 'status', 'items_count', 'grand_total', 'items']]);
});

test('jika session open tapi cart belum ada maka sistem membuat cart aktif', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/cart?device_id='.CART_DEVICE);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('pos_carts', ['user_id' => $kasir->id, 'status' => 'active']);
});

test('user tanpa session open tidak bisa melihat cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    // Tidak membuka session

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/cart?device_id='.CART_DEVICE);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Sesi kasir belum dibuka.');
});

// ──────────────────────────────────────────────
// Test 4-6: Role Access
// ──────────────────────────────────────────────

test('kasir bisa menambahkan produk ke cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store);

    Sanctum::actingAs($kasir);
    $response = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 1,
        'source' => 'manual',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items_count', 1);
});

test('owner bisa menambahkan produk ke cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    cartOpenSession($owner, $store);
    $product = cartMakeProduct($store);

    Sanctum::actingAs($owner);
    $response = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response->assertStatus(201)->assertJsonPath('success', true);
});

test('staff gudang manajer tidak bisa menambahkan item', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $staff = cartMakeUserWithRole('petugas_gudang', $store);
    $product = cartMakeProduct($store);

    Sanctum::actingAs($staff);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(403);
});

// ──────────────────────────────────────────────
// Test 7-10: Validasi Produk
// ──────────────────────────────────────────────

test('produk nonaktif tidak bisa masuk cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['status' => 'inactive']);

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Produk tidak aktif atau tidak dapat dijual.');
});

test('produk tidak sellable karena tanpa harga tidak bisa masuk cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['sell_price' => 0]);

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Produk belum memiliki harga jual aktif.');
});

test('produk dari store lain tidak bisa masuk cart', function () {
    ['user' => $owner, 'store' => $storeA] = cartMakeOwnerWithStore();
    ['user' => $owner2, 'store' => $storeB] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($storeA);
    cartOpenSession($kasir, $storeA);
    $productB = cartMakeProduct($storeB); // Produk dari store lain

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $productB->id,
        'quantity' => 1,
    ])->assertStatus(404);
});

test('produk tanpa harga tidak bisa masuk cart', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['sell_price' => 0]);

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertStatus(422);
});

// ──────────────────────────────────────────────
// Test 11-14: Stok dan Kalkulasi
// ──────────────────────────────────────────────

test('stok tidak cukup return 409', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 5); // Stok 5

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 10, // Minta 10
    ])->assertStatus(409)
        ->assertJsonPath('message', 'Stok tidak cukup.');
});

test('menambahkan produk yang sama akan merge quantity', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 100);

    Sanctum::actingAs($kasir);

    // Tambah pertama kali
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertStatus(201);

    // Tambah lagi produk yang sama
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertStatus(201);

    // Hanya 1 item di cart dengan quantity 5
    $this->assertDatabaseCount('pos_cart_items', 1);
    $this->assertDatabaseHas('pos_cart_items', ['product_id' => $product->id, 'quantity' => 5]);
});

test('subtotal item dihitung benar', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['sell_price' => 8000], 100);

    Sanctum::actingAs($kasir);
    $response = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response->assertStatus(201);

    // subtotal = 8000 * 3 = 24000
    $this->assertDatabaseHas('pos_cart_items', [
        'product_id' => $product->id,
        'quantity' => 3,
        'subtotal' => 24000,
    ]);
});

test('subtotal cart dihitung benar dari semua item', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $productA = cartMakeProduct($store, ['sell_price' => 5000], 100);
    $productB = cartMakeProduct($store, ['sell_price' => 10000], 100);

    Sanctum::actingAs($kasir);

    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $productA->id,
        'quantity' => 2, // 10000
    ]);

    $response = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $productB->id,
        'quantity' => 1, // 10000
    ]);

    // grand_total = 10000 + 10000 = 20000
    $response->assertStatus(201)
        ->assertJsonPath('data.grand_total', 20000);
});

// ──────────────────────────────────────────────
// Test 15-16: Update Item
// ──────────────────────────────────────────────

test('update quantity item berhasil', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    $session = cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['sell_price' => 5000], 100);

    $cart = PosCart::create([
        'store_id' => $store->id, 'session_id' => $session->id, 'user_id' => $kasir->id,
        'status' => 'active', 'subtotal' => 10000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 10000,
    ]);

    $item = PosCartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
        'unit_name' => 'Pcs', 'unit_price' => 5000, 'quantity' => 2, 'discount_value' => 0, 'subtotal' => 10000,
    ]);

    Sanctum::actingAs($kasir);
    $response = $this->patchJson("/api/v1/pos/cart/items/{$item->id}", [
        'device_id' => CART_DEVICE,
        'quantity' => 5,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grand_total', 25000);
});

test('update quantity melebihi stok return 409', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    $session = cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, ['sell_price' => 5000], 3); // Stok 3

    $cart = PosCart::create([
        'store_id' => $store->id, 'session_id' => $session->id, 'user_id' => $kasir->id,
        'status' => 'active', 'subtotal' => 5000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 5000,
    ]);

    $item = PosCartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
        'unit_name' => 'Pcs', 'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000,
    ]);

    Sanctum::actingAs($kasir);
    $this->patchJson("/api/v1/pos/cart/items/{$item->id}", [
        'device_id' => CART_DEVICE,
        'quantity' => 10, // Melebihi stok 3
    ])->assertStatus(409);
});

// ──────────────────────────────────────────────
// Test 17-18: Hapus Item dan Clear Cart
// ──────────────────────────────────────────────

test('hapus item cart berhasil', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    $session = cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store);

    $cart = PosCart::create([
        'store_id' => $store->id, 'session_id' => $session->id, 'user_id' => $kasir->id,
        'status' => 'active', 'subtotal' => 5000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 5000,
    ]);

    $item = PosCartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'sku' => $product->sku,
        'barcode' => $product->barcode, 'unit_name' => 'Pcs',
        'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000,
    ]);

    Sanctum::actingAs($kasir);
    $response = $this->deleteJson("/api/v1/pos/cart/items/{$item->id}", [
        'device_id' => CART_DEVICE,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.grand_total', 0);

    $this->assertDatabaseMissing('pos_cart_items', ['id' => $item->id]);
});

test('clear cart menghapus semua item dan reset total', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    $session = cartOpenSession($kasir, $store);
    $productA = cartMakeProduct($store, [], 50);
    $productB = cartMakeProduct($store, [], 50);

    $cart = PosCart::create([
        'store_id' => $store->id, 'session_id' => $session->id, 'user_id' => $kasir->id,
        'status' => 'active', 'subtotal' => 10000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 10000,
    ]);

    PosCartItem::insert([
        ['cart_id' => $cart->id, 'product_id' => $productA->id, 'product_name' => 'A', 'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000, 'created_at' => now(), 'updated_at' => now()],
        ['cart_id' => $cart->id, 'product_id' => $productB->id, 'product_name' => 'B', 'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Sanctum::actingAs($kasir);
    $response = $this->deleteJson('/api/v1/pos/cart', [
        'device_id' => CART_DEVICE,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.grand_total', 0);

    $this->assertDatabaseCount('pos_cart_items', 0);
    $this->assertDatabaseHas('pos_carts', ['id' => $cart->id, 'status' => 'active', 'grand_total' => 0]);
});

// ──────────────────────────────────────────────
// Test 19-20: Store Scope dan User Scope
// ──────────────────────────────────────────────

test('item cart user lain tidak bisa diubah', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasirA = cartMakeKasir($store);
    $kasirB = cartMakeKasir($store);

    $sessionA = cartOpenSession($kasirA, $store);
    $sessionB = cartOpenSession($kasirB, $store, 'POS-DEVICE-B');

    $product = cartMakeProduct($store);

    // Cart milik kasirA
    $cartA = PosCart::create([
        'store_id' => $store->id, 'session_id' => $sessionA->id, 'user_id' => $kasirA->id,
        'status' => 'active', 'subtotal' => 5000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 5000,
    ]);

    $item = PosCartItem::create([
        'cart_id' => $cartA->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'sku' => $product->sku,
        'barcode' => $product->barcode, 'unit_name' => 'Pcs',
        'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000,
    ]);

    // kasirB mencoba mengubah item milik kasirA
    Sanctum::actingAs($kasirB);
    $this->patchJson("/api/v1/pos/cart/items/{$item->id}", [
        'device_id' => 'POS-DEVICE-B',
        'quantity' => 5,
    ])->assertStatus(404); // Item bukan milik cart aktif kasirB
});

test('item cart store lain tidak bisa diubah', function () {
    ['user' => $owner, 'store' => $storeA] = cartMakeOwnerWithStore();
    ['user' => $owner2, 'store' => $storeB] = cartMakeOwnerWithStore();

    $kasirA = cartMakeKasir($storeA);
    $kasirB = cartMakeKasir($storeB);

    $sessionA = cartOpenSession($kasirA, $storeA);
    $sessionB = cartOpenSession($kasirB, $storeB);

    $productA = cartMakeProduct($storeA);

    $cartA = PosCart::create([
        'store_id' => $storeA->id, 'session_id' => $sessionA->id, 'user_id' => $kasirA->id,
        'status' => 'active', 'subtotal' => 5000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 5000,
    ]);

    $item = PosCartItem::create([
        'cart_id' => $cartA->id, 'product_id' => $productA->id,
        'product_name' => $productA->name, 'sku' => $productA->sku,
        'barcode' => $productA->barcode, 'unit_name' => 'Pcs',
        'unit_price' => 5000, 'quantity' => 1, 'discount_value' => 0, 'subtotal' => 5000,
    ]);

    // kasirB dari store lain mencoba ubah item cart storeA
    Sanctum::actingAs($kasirB);
    $this->patchJson("/api/v1/pos/cart/items/{$item->id}", [
        'device_id' => CART_DEVICE,
        'quantity' => 5,
    ])->assertStatus(404);
});

// ──────────────────────────────────────────────
// Test 21: Stok Tidak Berubah
// ──────────────────────────────────────────────

test('cart tidak mengubah stock_balances', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 100);

    $stockBefore = StockBalance::where('product_id', $product->id)->value('quantity');

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 5,
    ])->assertStatus(201);

    $stockAfter = StockBalance::where('product_id', $product->id)->value('quantity');

    expect((float) $stockAfter)->toBe((float) $stockBefore);
});

// ──────────────────────────────────────────────
// Test 22-24: Validasi Request
// ──────────────────────────────────────────────

test('request quantity wajib numeric min 0.001', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store);

    Sanctum::actingAs($kasir);

    // Quantity 0 harus ditolak
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 0,
    ])->assertStatus(422);

    // Quantity negatif harus ditolak
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => -1,
    ])->assertStatus(422);
});

test('device_id wajib diisi', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);

    Sanctum::actingAs($kasir);
    $this->getJson('/api/v1/pos/cart') // tanpa device_id
        ->assertStatus(422)
        ->assertJsonValidationErrors(['device_id']);
});

test('response memakai format success message data', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);

    Sanctum::actingAs($kasir);
    $response = $this->getJson('/api/v1/pos/cart?device_id='.CART_DEVICE);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data']);
    expect($response->json('success'))->toBeTrue();
});

// ──────────────────────────────────────────────
// Validasi Stok Tetap (Sesuai Audit)
// ──────────────────────────────────────────────

test('add cart stok tetap tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 50);

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertStatus(201);

    $balance = StockBalance::where('product_id', $product->id)->sum('quantity');
    expect((float) $balance)->toBe(50.0);
});

test('update cart stok tetap tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 50);

    Sanctum::actingAs($kasir);
    $addResponse = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $itemId = $addResponse->json('data.items.0.item_id');

    $this->patchJson("/api/v1/pos/cart/items/{$itemId}", [
        'device_id' => CART_DEVICE,
        'quantity' => 5,
    ])->assertStatus(200);

    $balance = StockBalance::where('product_id', $product->id)->sum('quantity');
    expect((float) $balance)->toBe(50.0);
});

test('delete cart item stok tetap tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 50);

    Sanctum::actingAs($kasir);
    $addResponse = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $itemId = $addResponse->json('data.items.0.item_id');

    $this->deleteJson("/api/v1/pos/cart/items/{$itemId}?device_id=".CART_DEVICE)->assertStatus(200);

    $balance = StockBalance::where('product_id', $product->id)->sum('quantity');
    expect((float) $balance)->toBe(50.0);
});

test('clear cart stok tetap tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = cartMakeOwnerWithStore();
    $kasir = cartMakeKasir($store);
    cartOpenSession($kasir, $store);
    $product = cartMakeProduct($store, [], 50);

    Sanctum::actingAs($kasir);
    $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => CART_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->deleteJson('/api/v1/pos/cart?device_id='.CART_DEVICE)->assertStatus(200);

    $balance = StockBalance::where('product_id', $product->id)->sum('quantity');
    expect((float) $balance)->toBe(50.0);
});
