<?php

use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Warehouse;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockTransferSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────

function psSeedRoles(): void
{
    foreach (['owner', 'kasir', 'staf_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function psMakeStore(): Store
{
    psSeedRoles();
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    return $store;
}

function psMakeUser(string $roleName, Store $store): User
{
    psSeedRoles();
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function psMakeWarehouse(Store $store, string $name = 'Gudang Utama'): Warehouse
{
    return Warehouse::create([
        'store_id' => $store->id,
        'name' => $name.' '.uniqid(),
        'code' => 'GDG-'.uniqid(),
        'warehouse_type' => 'warehouse',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
}

function psMakeCategory(): Category
{
    return Category::create([
        'name' => 'Kategori Test '.uniqid(),
        'status' => 'active',
        'created_by' => 1,
        'updated_by' => 1,
    ]);
}

function psMakeProduct(Store $store): Product
{
    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $cat = psMakeCategory();

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

function psCreateBalance(Store $store, Product $product, Warehouse $warehouse, float $qty): StockBalance
{
    return StockBalance::create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $qty,
    ]);
}

// ────────────────────────────────────────────────────
// Tests
// ────────────────────────────────────────────────────

it('POST /api/stok/pindah membuat draft transfer tanpa mengubah stok', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store, 'Gudang A');
    $whB = psMakeWarehouse($store, 'Gudang B');
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 7]],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('stock_transfer_sessions', [
        'store_id' => $store->id,
        'status' => 'draft',
    ]);

    // Stok TIDAK berubah setelah create draft
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whA->id,
        'quantity' => 20.0,
    ]);
});

it('POST /api/stok/pindah/{id}/send mengubah status draft ke selesai dan memutasikan stok', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store, 'Gudang A');
    $whB = psMakeWarehouse($store, 'Gudang B');
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);
    psCreateBalance($store, $product, $whB, 5.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    // Buat draft
    $draftResponse = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 7]],
    ]);
    $draftId = $draftResponse->json('data.id');

    // Kirim transfer
    $sendResponse = $this->postJson("/api/stok/pindah/{$draftId}/send", ['confirm' => true]);

    $sendResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.transfer.status', 'selesai');

    $this->assertDatabaseHas('stock_transfer_sessions', ['id' => $draftId, 'status' => 'selesai']);

    // Stok gudang asal berkurang 20 - 7 = 13
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whA->id,
        'quantity' => 13.0,
        'last_movement_type' => 'transfer_out',
    ]);

    // Stok gudang tujuan bertambah 5 + 7 = 12
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whB->id,
        'quantity' => 12.0,
        'last_movement_type' => 'transfer_in',
    ]);
});

it('POST /api/stok/pindah/{id}/send return 409 jika stok tidak cukup', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 3.0); // Hanya 3

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftResponse = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 10]], // Minta 10
    ]);
    $draftId = $draftResponse->json('data.id');

    $response = $this->postJson("/api/stok/pindah/{$draftId}/send", ['confirm' => true]);

    $response->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.items.0.available_stock', 3);

    // Transfer tetap draft, stok tidak berubah
    $this->assertDatabaseHas('stock_transfer_sessions', ['id' => $draftId, 'status' => 'draft']);
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whA->id,
        'quantity' => 3.0,
    ]);
});

it('POST /api/stok/pindah/{id}/send gagal jika sudah selesai (bukan draft)', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftId = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ])->json('data.id');

    $this->postJson("/api/stok/pindah/{$draftId}/send", ['confirm' => true])->assertStatus(200);

    // Coba kirim lagi
    $response = $this->postJson("/api/stok/pindah/{$draftId}/send", ['confirm' => true]);
    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('POST /api/stok/pindah/{id}/cancel membatalkan draft tanpa reversal stok', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftId = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 7]],
    ])->json('data.id');

    $response = $this->postJson("/api/stok/pindah/{$draftId}/cancel", [
        'confirm' => true,
        'reason' => 'Salah gudang tujuan',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.transfer.status', 'dibatalkan');

    $this->assertDatabaseHas('stock_transfer_sessions', [
        'id' => $draftId,
        'status' => 'dibatalkan',
        'cancel_reason' => 'Salah gudang tujuan',
    ]);

    // Stok tidak berubah
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whA->id,
        'quantity' => 20.0,
    ]);
});

it('POST /api/stok/pindah/{id}/cancel pada transfer selesai melakukan reversal stok', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftId = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 7]],
    ])->json('data.id');

    $this->postJson("/api/stok/pindah/{$draftId}/send", ['confirm' => true])->assertStatus(200);

    // Verifikasi stok setelah send
    $this->assertDatabaseHas('stock_balances', ['product_id' => $product->id, 'warehouse_id' => $whA->id, 'quantity' => 13.0]);
    $this->assertDatabaseHas('stock_balances', ['product_id' => $product->id, 'warehouse_id' => $whB->id, 'quantity' => 7.0]);

    // Batalkan transfer yang sudah selesai
    $response = $this->postJson("/api/stok/pindah/{$draftId}/cancel", [
        'confirm' => true,
        'reason' => 'Pengiriman salah produk',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.transfer.status', 'dibatalkan');

    // Reversal: stok kembali ke kondisi asal
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whA->id,
        'quantity' => 20.0,
        'last_movement_type' => 'reversal_transfer',
    ]);
    $this->assertDatabaseHas('stock_balances', [
        'product_id' => $product->id,
        'warehouse_id' => $whB->id,
        'quantity' => 0.0,
        'last_movement_type' => 'reversal_transfer',
    ]);
});

it('POST /api/stok/pindah/{id}/cancel gagal jika sudah dibatalkan', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    psCreateBalance($store, $product, $whA, 20.0);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftId = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ])->json('data.id');

    $this->postJson("/api/stok/pindah/{$draftId}/cancel", ['confirm' => true, 'reason' => 'Pertama'])->assertStatus(200);

    // Coba batalkan lagi
    $response = $this->postJson("/api/stok/pindah/{$draftId}/cancel", ['confirm' => true, 'reason' => 'Kedua']);
    $response->assertStatus(422);
});

it('cancel tanpa reason return 422', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);

    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $draftId = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ])->json('data.id');

    $response = $this->postJson("/api/stok/pindah/{$draftId}/cancel", [
        'confirm' => true,
        'reason' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

it('user tanpa profile.store_id membuat draft return 403', function () {
    psSeedRoles();
    $role = Role::where('name', 'staf_gudang')->first();
    $user = User::factory()->create(['role_id' => $role->id]);

    Sanctum::actingAs($user->fresh(['role', 'profile']));

    $response = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => 1,
        'to_warehouse_id' => 2,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => 1, 'quantity' => 5]],
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseCount('stock_transfer_sessions', 0);
});

it('create draft tanpa gudang return 422', function () {
    $store = psMakeStore();
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);
    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => null,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['from_warehouse_id']);
});

it('create draft dengan gudang asal sama dengan tujuan return 422', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $product = psMakeProduct($store);
    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whA->id,
        'transfer_date' => '2026-08-21',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['to_warehouse_id']);
});

it('create draft dengan produk duplikat return 422', function () {
    $store = psMakeStore();
    $whA = psMakeWarehouse($store);
    $whB = psMakeWarehouse($store);
    $product = psMakeProduct($store);
    $user = psMakeUser('staf_gudang', $store);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/stok/pindah', [
        'from_warehouse_id' => $whA->id,
        'to_warehouse_id' => $whB->id,
        'transfer_date' => '2026-08-21',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);
});

it('user dari store lain tidak bisa lihat detail transfer return 404', function () {
    $storeA = psMakeStore();
    $storeB = psMakeStore();

    $whA1 = psMakeWarehouse($storeA);
    $whA2 = psMakeWarehouse($storeA);

    $session = StockTransferSession::create([
        'store_id' => $storeA->id,
        'transfer_code' => 'TRF-20260821-001',
        'from_warehouse_id' => $whA1->id,
        'to_warehouse_id' => $whA2->id,
        'transfer_date' => '2026-08-21',
        'status' => 'draft',
        'total_items' => 0,
        'created_by' => $storeA->owner_id,
    ]);

    $userB = psMakeUser('staf_gudang', $storeB);
    Sanctum::actingAs($userB);

    $response = $this->getJson("/api/stok/pindah/{$session->id}");
    $response->assertStatus(404);
});

it('list transfer hanya menampilkan data store aktif user', function () {
    $storeA = psMakeStore();
    $storeB = psMakeStore();

    $whA1 = psMakeWarehouse($storeA);
    $whA2 = psMakeWarehouse($storeA);
    $whB1 = psMakeWarehouse($storeB);
    $whB2 = psMakeWarehouse($storeB);

    StockTransferSession::create([
        'store_id' => $storeA->id,
        'transfer_code' => 'TRF-A',
        'from_warehouse_id' => $whA1->id,
        'to_warehouse_id' => $whA2->id,
        'transfer_date' => '2026-08-21',
        'status' => 'draft',
        'total_items' => 0,
        'created_by' => $storeA->owner_id,
    ]);

    StockTransferSession::create([
        'store_id' => $storeB->id,
        'transfer_code' => 'TRF-B',
        'from_warehouse_id' => $whB1->id,
        'to_warehouse_id' => $whB2->id,
        'transfer_date' => '2026-08-21',
        'status' => 'draft',
        'total_items' => 0,
        'created_by' => $storeB->owner_id,
    ]);

    $userA = psMakeUser('staf_gudang', $storeA);
    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/stok/pindah');
    $response->assertStatus(200);

    $items = $response->json('data.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['transfer_code'])->toBe('TRF-A');
});

it('master data context=transfer mengembalikan gudang dan produk milik store aktif', function () {
    $storeA = psMakeStore();
    $storeB = psMakeStore();

    $whA = psMakeWarehouse($storeA, 'Gudang A');
    $whB = psMakeWarehouse($storeB, 'Gudang B');

    $prodA = psMakeProduct($storeA);
    $prodB = psMakeProduct($storeB);

    $userA = psMakeUser('staf_gudang', $storeA);
    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/stok/master-data?context=transfer');
    $response->assertStatus(200);

    $whIds = collect($response->json('data.warehouses'))->pluck('id')->toArray();
    $prodIds = collect($response->json('data.products'))->pluck('id')->toArray();

    expect($whIds)->toContain((string) $whA->id);
    expect($whIds)->not->toContain((string) $whB->id);

    expect($prodIds)->toContain((string) $prodA->id);
    expect($prodIds)->not->toContain((string) $prodB->id);
});
