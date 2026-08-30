<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Stock\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const CHECKOUT_DEVICE = 'POS-CHECKOUT-TEST-001';

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Helpers
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function coSeedRoles(): void
{
    foreach (['owner', 'kasir', 'petugas_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function coMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function coMakeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function coOpenSession(User $user, Store $store, string $deviceId = CHECKOUT_DEVICE): PosSession
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

function coMakeProduct(Store $store, array $attrs = []): Product
{
    $cat = Category::firstOrCreate(
        ['name' => 'Kategori Checkout'],
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
        'name' => 'Produk Checkout',
        'sku' => 'CHK-'.uniqid(),
        'barcode' => 'BAR-CHK-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 10000,
        'buy_price' => 8000,
        'initial_stock' => $attrs['initial_stock'] ?? 10,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attrs));

    return $product;
}

function coMakeActiveCart(PosSession $session): PosCart
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

function coAddItemToCart(PosCart $cart, Product $product, float $qty = 1.0): PosCartItem
{
    $subtotal = (float) $product->sell_price * $qty;

    $item = PosCartItem::create([
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

    // update cart totals
    $cart->update([
        'subtotal' => $cart->subtotal + $subtotal,
        'grand_total' => $cart->grand_total + $subtotal,
    ]);

    return $item;
}

beforeEach(function () {
    coSeedRoles();
});

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Tests
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

it('get payment methods berjalan', function () {
    ['user' => $owner] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/pos/payment-methods');
    $response->assertOk()->assertJsonPath('success', true);

    // minimal ada cash
    $methods = $response->json('data');
    expect($methods)->not->toBeEmpty();
});

it('kasir bisa checkout cart valid', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    $kasir = coMakeKasir($store);
    Sanctum::actingAs($kasir);

    $session = coOpenSession($kasir, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000]);
    coAddItemToCart($cart, $product, 1);

    $idempotency = uniqid();

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => $idempotency,
        'payments' => [
            ['payment_method' => 'cash', 'amount' => 10000],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grand_total', 10000.0);
});

it('owner bisa checkout cart valid', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 5000]);
    coAddItemToCart($cart, $product, 2);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [
            ['payment_method' => 'cash', 'amount' => 10000],
        ],
    ]);

    $response->assertStatus(201);
});

it('user tanpa session open tidak bisa checkout', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);

    // tutup session
    $session->update(['status' => 'closed']);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ]);

    $response->assertStatus(409); // Conflict Http Exception
});

it('cart kosong tidak bisa checkout', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ]);

    $response->assertStatus(422); // Unprocessable
});

it('cart milik user/store lain tidak bisa checkout', function () {
    ['user' => $owner1, 'store' => $store1] = coMakeOwnerWithStore();
    ['user' => $owner2, 'store' => $store2] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner1);

    $session1 = coOpenSession($owner1, $store1);

    // Cart milik store2
    $session2 = coOpenSession($owner2, $store2);
    $cart2 = coMakeActiveCart($session2);
    $product2 = coMakeProduct($store2);
    coAddItemToCart($cart2, $product2, 1);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart2->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ]);

    $response->assertStatus(409); // Conflict: Cart bukan milik toko ini
});

it('total pembayaran kurang return 422', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 15000]);
    coAddItemToCart($cart, $product, 1);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]], // 10000 < 15000
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Total pembayaran kurang dari grand total.');
});

it('cash lebih besar menghasilkan change_amount', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 15000]);
    coAddItemToCart($cart, $product, 1);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 20000]],
    ]);

    $response->assertStatus(201);
    expect((float) $response->json('data.change_amount'))->toBe(5000.0);
    expect((float) $response->json('data.paid_amount'))->toBe(20000.0);
});

it('split payment berhasil jika total cukup', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 25000]);
    coAddItemToCart($cart, $product, 1);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [
            ['payment_method' => 'cash', 'amount' => 10000],
            ['payment_method' => 'qris', 'amount' => 20000],
        ],
    ]);

    $response->assertStatus(201);
    expect((float) $response->json('data.change_amount'))->toBe(5000.0);
    expect((float) $response->json('data.paid_amount'))->toBe(30000.0);
});

it('checkout membuat pos_transactions dan relasi', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 20000]);
    coAddItemToCart($cart, $product, 1);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 20000]],
    ]);

    $response->assertStatus(201);

    $tx = PosTransaction::first();
    expect($tx)->not->toBeNull();
    expect($tx->transaction_number)->not->toBeNull();

    // 10. item
    expect($tx->items()->count())->toBe(1);

    // 11. payment
    expect($tx->payments()->count())->toBe(1);

    // 12. receipt
    expect($tx->receipt()->exists())->toBeTrue();
});

it('checkout mengurangi stock dan membuat stock_movements', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 10]);
    coAddItemToCart($cart, $product, 3);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 30000]],
    ]);

    $response->assertStatus(201);

    // Stock berkurang pada products.initial_stock
    expect((int) $product->fresh()->initial_stock)->toBe(7); // 10 - 3

    // stock movement terbuat
    $movement = StockMovement::where('product_id', $product->id)->first();
    expect($movement)->not->toBeNull();
    expect((float) $movement->quantity)->toBe(3.0);
    expect($movement->movement_type)->toBe('stock_out');
});

it('cart berubah status checked_out', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000]);
    coAddItemToCart($cart, $product, 1);

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ]);

    expect($cart->fresh()->status)->toBe('checked_out');
});

it('retry idempotency_key sama tidak membuat transaksi baru', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000]);
    coAddItemToCart($cart, $product, 1);
    $idem = uniqid();

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => $idem,
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ])->assertStatus(201);

    // Hit lagi
    $res2 = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => $idem,
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ]);

    $res2->assertStatus(200)
        ->assertJsonPath('message', 'Transaksi sudah pernah diproses.');

    expect(PosTransaction::count())->toBe(1);
});

it('stok kurang saat checkout return 409', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    // initial stock 5
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 5]);
    coAddItemToCart($cart, $product, 10); // beli 10

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 100000]],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Stok berubah saat checkout.');
});

it('harga/total dihitung ulang via PricingService (cart dimanipulasi)', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000]);
    $item = coAddItemToCart($cart, $product, 2); // 20k

    // user coba hack total
    $item->update(['subtotal' => 100]);
    $cart->update(['grand_total' => 100, 'subtotal' => 100]);

    // bayar dengan 100, harusnya di tolak 422 karena butuh 20000
    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 100]],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Total pembayaran kurang dari grand total.');
});

it('role selain kasir/owner tidak bisa checkout', function () {
    ['store' => $store] = coMakeOwnerWithStore();
    $gudang = User::factory()->create([
        'role_id' => Role::where('name', 'petugas_gudang')->first()->id,
        'is_active' => true,
    ]);
    UserProfile::factory()->create(['user_id' => $gudang->id, 'store_id' => $store->id]);
    Sanctum::actingAs($gudang->fresh(['role', 'profile']));

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => 999,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
    ])->assertStatus(403);
});

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Audit Integritas Stok (MVP: products.initial_stock)
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

it('checkout sukses stok berkurang sesuai qty', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 20]);
    coAddItemToCart($cart, $product, 5);

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 50000]],
    ])->assertStatus(201);

    expect((int) $product->fresh()->initial_stock)->toBe(15); // 20 - 5
});

it('checkout sukses membuat stock_movement dengan movement_type stock_out', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 10]);
    coAddItemToCart($cart, $product, 3);

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 30000]],
    ])->assertStatus(201);

    $movement = StockMovement::where('store_id', $store->id)
        ->where('product_id', $product->id)
        ->where('movement_type', 'stock_out')
        ->first();

    expect($movement)->not->toBeNull();
    expect((float) $movement->quantity)->toBe(3.0);
    expect((float) $movement->before_quantity)->toBe(10.0);
    expect((float) $movement->after_quantity)->toBe(7.0);
});

it('checkout gagal payment kurang stok tetap tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 15000, 'initial_stock' => 10]);
    coAddItemToCart($cart, $product, 2); // grand_total = 30000

    $stockBefore = (int) $product->fresh()->initial_stock;

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 10000]], // kurang: 10000 < 30000
    ])->assertStatus(422);

    expect((int) $product->fresh()->initial_stock)->toBe($stockBefore); // stok tetap
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(0);
});

it('checkout gagal stok kurang stok tidak berkurang', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 3]);
    coAddItemToCart($cart, $product, 10); // request 10, stok hanya 3

    $stockBefore = (int) $product->fresh()->initial_stock;

    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => uniqid(),
        'payments' => [['payment_method' => 'cash', 'amount' => 100000]],
    ])->assertStatus(409);

    expect((int) $product->fresh()->initial_stock)->toBe($stockBefore); // stok tetap
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(0);
});

it('retry checkout idempotency_key sama stok tidak berkurang dua kali', function () {
    ['user' => $owner, 'store' => $store] = coMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $session = coOpenSession($owner, $store);
    $cart = coMakeActiveCart($session);
    $product = coMakeProduct($store, ['sell_price' => 10000, 'initial_stock' => 20]);
    coAddItemToCart($cart, $product, 4); // grand_total = 40000

    $idem = uniqid('idem-');

    // Checkout pertama â€” sukses
    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => $idem,
        'payments' => [['payment_method' => 'cash', 'amount' => 40000]],
    ])->assertStatus(201);

    expect((int) $product->fresh()->initial_stock)->toBe(16); // 20 - 4

    // Retry dengan idempotency_key yang sama
    $this->postJson('/api/v1/pos/checkout', [
        'device_id' => CHECKOUT_DEVICE,
        'cart_id' => $cart->id,
        'idempotency_key' => $idem,
        'payments' => [['payment_method' => 'cash', 'amount' => 40000]],
    ])->assertStatus(200); // return existing transaction

    // Stok TIDAK berkurang dua kali
    expect((int) $product->fresh()->initial_stock)->toBe(16);
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(1);
    expect(PosTransaction::count())->toBe(1);
});
