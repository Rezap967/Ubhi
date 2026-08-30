<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers — prefixed "pe_" agar tidak konflik dengan PosCartTest
// ──────────────────────────────────────────────

const PRICING_DEVICE = 'POS-PRICING-TEST-001';

function peSeedRoles(): void
{
    foreach (['owner', 'kasir', 'petugas_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function peMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function peMakeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function peOpenSession(User $user, Store $store, string $deviceId = PRICING_DEVICE): PosSession
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

function peMakeProduct(Store $store, array $attrs = []): Product
{
    $cat = Category::firstOrCreate(
        ['name' => 'Kategori Pricing'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );
    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    return Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Pricing Test',
        'sku' => 'PRICE-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 5000,
        'buy_price' => 4000,
        'initial_stock' => 100,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attrs));
}

function peMakeActiveCart(PosSession $session): PosCart
{
    return PosCart::create([
        'store_id' => $session->store_id,
        'session_id' => $session->id,
        'user_id' => $session->user_id,
        'customer_id' => null,
        'status' => 'active',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
    ]);
}

function peAddItemToCart(PosCart $cart, Product $product, float $qty = 1.0): PosCartItem
{
    $subtotal = (float) $product->sell_price * $qty;

    return PosCartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'barcode' => $product->barcode,
        'unit_name' => 'Pcs',
        'unit_price' => $product->sell_price,
        'quantity' => $qty,
        'discount_value' => 0,
        'subtotal' => $subtotal,
        'source' => 'manual',
    ]);
}

// ──────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────

beforeEach(function () {
    peSeedRoles();
});

// ──────────────────────────────────────────────
// Tests: GET /api/v1/pos/cart/summary
// ──────────────────────────────────────────────

it('user dengan session open dapat mengambil cart summary', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store);
    peAddItemToCart($cart, $product, 2);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [
            'cart_id', 'session_id', 'items_count',
            'subtotal', 'discount_total', 'tax_total', 'grand_total',
            'formatted' => ['subtotal', 'discount_total', 'tax_total', 'grand_total'],
        ]]);
});

it('user tanpa session open mendapat 409', function () {
    ['user' => $user] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE)
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

it('cart kosong menghasilkan subtotal 0 dan grand_total 0', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    peOpenSession($user, $store);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect($response->json('data.items_count'))->toBe(0);
    expect((float) $response->json('data.subtotal'))->toBe(0.0);
    expect((float) $response->json('data.grand_total'))->toBe(0.0);
});

it('cart dengan 1 item menghitung subtotal benar', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store, ['sell_price' => 10000]);
    peAddItemToCart($cart, $product, 3.0);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect($response->json('data.items_count'))->toBe(1);
    expect((float) $response->json('data.subtotal'))->toBe(30000.0);
    expect((float) $response->json('data.grand_total'))->toBe(30000.0);
});

it('cart dengan beberapa item menghitung subtotal benar', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product1 = peMakeProduct($store, ['sell_price' => 5000]);
    $product2 = peMakeProduct($store, ['sell_price' => 8000]);
    peAddItemToCart($cart, $product1, 2.0); // 10000
    peAddItemToCart($cart, $product2, 3.0); // 24000

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect($response->json('data.items_count'))->toBe(2);
    expect((float) $response->json('data.subtotal'))->toBe(34000.0);
    expect((float) $response->json('data.grand_total'))->toBe(34000.0);
});

it('quantity decimal dihitung dengan benar', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store, ['sell_price' => 10000]);
    peAddItemToCart($cart, $product, 1.5); // 15000

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect((float) $response->json('data.subtotal'))->toBe(15000.0);
    expect((float) $response->json('data.grand_total'))->toBe(15000.0);
});

it('discount_total default 0 pada tahap ini', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store);
    peAddItemToCart($cart, $product);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect((float) $response->json('data.discount_total'))->toBe(0.0);
});

it('tax_total default 0 pada tahap ini', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store);
    peAddItemToCart($cart, $product);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect((float) $response->json('data.tax_total'))->toBe(0.0);
});

it('grand_total tidak pernah negatif', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);

    // Force negatif: subtotal manual negatif, tapi tidak ada items jadi recalculate akan set 0
    $cart->update(['subtotal' => -9999, 'grand_total' => -9999]);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect((float) $response->json('data.grand_total'))->toBeGreaterThanOrEqual(0.0);
});

it('summary hanya bisa diakses role kasir atau owner', function () {
    ['store' => $store] = peMakeOwnerWithStore();
    $gudang = User::factory()->create([
        'role_id' => Role::where('name', 'petugas_gudang')->first()->id,
        'is_active' => true,
    ]);
    UserProfile::factory()->create(['user_id' => $gudang->id, 'store_id' => $store->id]);
    Sanctum::actingAs($gudang->fresh(['role', 'profile']));

    $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE)
        ->assertStatus(403);
});

it('formatted money ada di response summary', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store, ['sell_price' => 6000]);
    peAddItemToCart($cart, $product);

    $response = $this->getJson('/api/v1/pos/cart/summary?device_id='.PRICING_DEVICE);

    $response->assertOk()
        ->assertJsonPath('data.formatted.subtotal', 'Rp 6.000')
        ->assertJsonPath('data.formatted.grand_total', 'Rp 6.000');
});

// ──────────────────────────────────────────────
// Tests: Integrasi via Endpoint Cart
// ──────────────────────────────────────────────

it('add item memperbarui subtotal dan grand_total', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    peOpenSession($user, $store);
    $product = peMakeProduct($store, ['sell_price' => 5000]);

    $response = $this->postJson('/api/v1/pos/cart/items', [
        'device_id' => PRICING_DEVICE,
        'product_id' => $product->id,
        'quantity' => 2,
        'source' => 'manual',
    ]);

    $response->assertStatus(201);
    expect((float) $response->json('data.subtotal'))->toBe(10000.0);
    expect((float) $response->json('data.grand_total'))->toBe(10000.0);
});

it('update quantity memperbarui subtotal dan grand_total', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store, ['sell_price' => 5000]);
    $item = peAddItemToCart($cart, $product, 1.0);

    $response = $this->patchJson('/api/v1/pos/cart/items/'.$item->id, [
        'device_id' => PRICING_DEVICE,
        'quantity' => 4,
    ]);

    $response->assertOk();
    expect((float) $response->json('data.subtotal'))->toBe(20000.0);
    expect((float) $response->json('data.grand_total'))->toBe(20000.0);
});

it('delete item memperbarui subtotal dan grand_total', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product1 = peMakeProduct($store, ['sell_price' => 5000]);
    $product2 = peMakeProduct($store, ['sell_price' => 3000]);
    peAddItemToCart($cart, $product1, 2.0); // 10000
    $item2 = peAddItemToCart($cart, $product2, 1.0); // 3000

    $response = $this->deleteJson('/api/v1/pos/cart/items/'.$item2->id.'?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect((float) $response->json('data.subtotal'))->toBe(10000.0);
    expect((float) $response->json('data.grand_total'))->toBe(10000.0);
});

it('clear cart mereset semua total menjadi 0', function () {
    ['user' => $user, 'store' => $store] = peMakeOwnerWithStore();
    Sanctum::actingAs($user);

    $session = peOpenSession($user, $store);
    $cart = peMakeActiveCart($session);
    $product = peMakeProduct($store, ['sell_price' => 5000]);
    peAddItemToCart($cart, $product, 3.0);

    $response = $this->deleteJson('/api/v1/pos/cart?device_id='.PRICING_DEVICE);

    $response->assertOk();
    expect($response->json('data.items_count'))->toBe(0);
    expect((float) $response->json('data.subtotal'))->toBe(0.0);
    expect((float) $response->json('data.grand_total'))->toBe(0.0);
});
