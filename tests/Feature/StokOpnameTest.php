<?php

use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Warehouse;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockOpnameItem;
use App\Models\Stock\StockOpnameSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────

function opSeedRoles(): void
{
    foreach (['owner', 'kasir', 'staf_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function opMakeStore(): Store
{
    opSeedRoles();
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    return $store;
}

function opMakeUser(string $roleName, Store $store): User
{
    opSeedRoles();
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function opMakeWarehouse(Store $store): Warehouse
{
    return Warehouse::create([
        'store_id' => $store->id,
        'name' => 'Gudang Test '.uniqid(),
        'code' => 'GDG-'.uniqid(),
        'warehouse_type' => 'warehouse',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
}

function opMakeCategory(): Category
{
    return Category::create([
        'name' => 'Kategori Test '.uniqid(),
        'status' => 'active',
        'created_by' => 1,
        'updated_by' => 1,
    ]);
}

function opMakeProduct(Store $store, ?Category $category = null): Product
{
    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $cat = $category ?? opMakeCategory();

    return Product::create([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Test '.uniqid(),
        'sku' => 'SKU-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 10000,
        'buy_price' => 8000,
        'initial_stock' => 0,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
}

function opCreateBalance(Store $store, Product $product, ?Warehouse $warehouse, float $qty): StockBalance
{
    return StockBalance::create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse?->id,
        'quantity' => $qty,
    ]);
}

// ────────────────────────────────────────────────────
// Tests
// ────────────────────────────────────────────────────

it('staf_gudang dengan profile.store_id bisa membuat sesi opname all_products', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    opCreateBalance($store, $product, null, 10.0);

    $user = opMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
        'notes' => 'Test notes',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'berjalan');

    $this->assertDatabaseHas('stock_opname_sessions', [
        'store_id' => $store->id,
        'session_name' => 'Opname Test',
        'status' => 'berjalan',
    ]);

    // Pastikan items terbuat
    $session = StockOpnameSession::where('store_id', $store->id)->first();
    $this->assertGreaterThan(0, $session->items()->count());

    // Pastikan stock_balances tidak berubah
    $this->assertDatabaseHas('stock_balances', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 10.0,
    ]);
});

it('user tanpa profile.store_id mendapat 403 saat membuat sesi', function () {
    opSeedRoles();
    $role = Role::where('name', 'staf_gudang')->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    // Tidak ada profile/store_id

    Sanctum::actingAs($user->fresh(['role', 'profile']));

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseCount('stock_opname_sessions', 0);
});

it('create sesi scope per_warehouse tanpa warehouse_id return 422', function () {
    $store = opMakeStore();
    $user = opMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Gudang',
        'scope_type' => 'per_warehouse',
        'warehouse_id' => null,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('create sesi scope per_category tanpa category_id return 422', function () {
    $store = opMakeStore();
    $user = opMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Kategori',
        'scope_type' => 'per_category',
        'category_id' => null,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);
});

it('update physical_stock item — tersimpan, difference benar, stock_balances tidak berubah', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    $balance = opCreateBalance($store, $product, null, 10.0);

    $user = opMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    // Buat sesi dulu
    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    $item = StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'system_stock' => 10.0,
    ]);

    $response = $this->patchJson("/api/stok/opname/{$session->id}/items/{$item->id}", [
        'physical_stock' => 8,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.physical_stock', 8)
        ->assertJsonPath('data.difference', -2);

    $this->assertDatabaseHas('stock_opname_items', [
        'id' => $item->id,
        'physical_stock' => 8.0,
        'difference' => -2.0,
    ]);

    // stock_balances tidak berubah
    $this->assertDatabaseHas('stock_balances', [
        'id' => $balance->id,
        'quantity' => 10.0,
    ]);
});

it('update item pada session disetujui return 409', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    $user = opMakeUser('staf_gudang', $store);
    $manager = opMakeUser('manajer', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
        'status' => 'disetujui',
        'created_by' => $user->id,
        'approved_by' => $manager->id,
        'approved_at' => now(),
    ]);

    $item = StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'system_stock' => 10.0,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson("/api/stok/opname/{$session->id}/items/{$item->id}", [
        'physical_stock' => 8,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('success', false);
});

it('staf_gudang mencoba approve return 403', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    $user = opMakeUser('staf_gudang', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/stok/opname/{$session->id}/approve", [
        'confirm' => true,
    ]);

    $response->assertStatus(403);
});

it('manajer approve session berjalan — status jadi disetujui dan stock_balances disesuaikan', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    opCreateBalance($store, $product, null, 10.0);

    $user = opMakeUser('staf_gudang', $store);
    $manager = opMakeUser('manajer', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Test',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'system_stock' => 10.0,
        'physical_stock' => 8.0,
        'difference' => -2.0,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/stok/opname/{$session->id}/approve", [
        'confirm' => true,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'disetujui');

    $this->assertDatabaseHas('stock_opname_sessions', [
        'id' => $session->id,
        'status' => 'disetujui',
        'approved_by' => $manager->id,
    ]);

    // stock_balances harus disesuaikan ke physical_stock
    $this->assertDatabaseHas('stock_balances', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'quantity' => 8.0,
        'last_movement_type' => 'opname_adjustment_minus',
        'last_reference_no' => 'OPN-20260821-001',
    ]);
});

it('approve session dua kali — approve kedua return 409 dan stock_balances tidak berubah dua kali', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    opCreateBalance($store, $product, null, 10.0);

    $user = opMakeUser('staf_gudang', $store);
    $manager = opMakeUser('manajer', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-002',
        'session_name' => 'Opname Test 2',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'system_stock' => 10.0,
        'physical_stock' => 5.0,
        'difference' => -5.0,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($manager);

    // Approve pertama
    $this->postJson("/api/stok/opname/{$session->id}/approve", ['confirm' => true])
        ->assertStatus(200);

    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'quantity' => 5.0,
    ]);

    // Approve kedua
    $response = $this->postJson("/api/stok/opname/{$session->id}/approve", ['confirm' => true]);
    $response->assertStatus(409);

    // Quantity tidak berubah lagi
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'quantity' => 5.0,
    ]);
});

it('user dari store lain tidak bisa akses session store ini — return 404', function () {
    $storeA = opMakeStore();
    $storeB = opMakeStore();
    $product = opMakeProduct($storeA);

    $userB = opMakeUser('staf_gudang', $storeB);

    $session = StockOpnameSession::create([
        'store_id' => $storeA->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Store A',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $storeA->owner_id,
    ]);

    Sanctum::actingAs($userB);

    $response = $this->getJson("/api/stok/opname/{$session->id}");
    $response->assertStatus(404);
});

it('item dengan physical_stock null saat approve dilewati — stock_balances item tersebut tidak berubah', function () {
    $store = opMakeStore();
    $productA = opMakeProduct($store);
    $productB = opMakeProduct($store);

    opCreateBalance($store, $productA, null, 20.0);
    opCreateBalance($store, $productB, null, 15.0);

    $user = opMakeUser('staf_gudang', $store);
    $manager = opMakeUser('manajer', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-003',
        'session_name' => 'Opname Test 3',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    // ProductA: ada physical_stock
    StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $productA->id,
        'warehouse_id' => null,
        'system_stock' => 20.0,
        'physical_stock' => 18.0,
        'difference' => -2.0,
        'updated_by' => $user->id,
    ]);

    // ProductB: physical_stock NULL (belum diisi)
    StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $productB->id,
        'warehouse_id' => null,
        'system_stock' => 15.0,
        'physical_stock' => null,
        'difference' => null,
    ]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/stok/opname/{$session->id}/approve", ['confirm' => true])
        ->assertStatus(200);

    // ProductA disesuaikan
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $productA->id,
        'quantity' => 18.0,
    ]);

    // ProductB tidak berubah
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $productB->id,
        'quantity' => 15.0,
    ]);
});

it('difference 0 saat approve — stock_balances metadata tidak berubah tapi session tetap disetujui', function () {
    $store = opMakeStore();
    $product = opMakeProduct($store);
    opCreateBalance($store, $product, null, 10.0);

    $user = opMakeUser('staf_gudang', $store);
    $manager = opMakeUser('manajer', $store);

    $session = StockOpnameSession::create([
        'store_id' => $store->id,
        'session_code' => 'OPN-20260821-004',
        'session_name' => 'Opname Test 4',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $user->id,
    ]);

    StockOpnameItem::create([
        'session_id' => $session->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'system_stock' => 10.0,
        'physical_stock' => 10.0,  // Sama — difference = 0
        'difference' => 0.0,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/stok/opname/{$session->id}/approve", ['confirm' => true]);
    $response->assertStatus(200);

    $this->assertDatabaseHas('stock_opname_sessions', [
        'id' => $session->id,
        'status' => 'disetujui',
    ]);

    // Quantity tidak berubah (karena skip)
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'quantity' => 10.0,
    ]);
});

it('validasi warehouse dari store lain return 422', function () {
    $storeA = opMakeStore();
    $storeB = opMakeStore();

    $warehouseB = opMakeWarehouse($storeB); // Warehouse milik store B

    $user = opMakeUser('staf_gudang', $storeA);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Gudang Salah',
        'scope_type' => 'per_warehouse',
        'warehouse_id' => $warehouseB->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('list opname hanya menampilkan sesi milik store aktif user', function () {
    $storeA = opMakeStore();
    $storeB = opMakeStore();

    $userA = opMakeUser('staf_gudang', $storeA);

    StockOpnameSession::create([
        'store_id' => $storeA->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Store A',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $storeA->owner_id,
    ]);

    StockOpnameSession::create([
        'store_id' => $storeB->id,
        'session_code' => 'OPN-20260821-001',
        'session_name' => 'Opname Store B',
        'scope_type' => 'all_products',
        'status' => 'berjalan',
        'created_by' => $storeB->owner_id,
    ]);

    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/stok/opname');
    $response->assertStatus(200);

    $items = $response->json('data.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['session_name'])->toBe('Opname Store A');
});

it('create sesi scope all_products otomatis mengambil default warehouse toko', function () {
    $store = opMakeStore();
    // Default warehouse dari seeder/toko
    $defaultWarehouse = Warehouse::create([
        'store_id' => $store->id,
        'name' => 'Stok Di Toko',
        'code' => 'TOKO-01',
        'warehouse_type' => 'store_stock',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);

    $product = opMakeProduct($store);
    opCreateBalance($store, $product, $defaultWarehouse, 25.0);

    $user = opMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/opname', [
        'session_name' => 'Opname Semua Produk Default Warehouse',
        'scope_type' => 'all_products',
    ]);

    $response->assertStatus(201);

    $session = StockOpnameSession::where('store_id', $store->id)
        ->where('session_name', 'Opname Semua Produk Default Warehouse')
        ->first();

    expect($session->warehouse_id)->toBe($defaultWarehouse->id);

    $item = $session->items()->first();
    expect($item->warehouse_id)->toBe($defaultWarehouse->id);
    expect((float) $item->system_stock)->toBe(25.0);
});
