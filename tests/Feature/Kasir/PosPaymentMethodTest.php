<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\PaymentMethod;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['owner', 'kasir', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
});

it('GET /pos/payment-methods selalu menampilkan Tunai', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/pos/payment-methods');

    $response->assertOk()->assertJsonPath('success', true);
    $methods = $response->json('data');
    expect($methods[0]['code'])->toBe('cash');
    expect($methods[0]['name'])->toBe('Tunai');
});

it('cashless aktif muncul', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'QRIS BCA',
        'code' => 'qris_bca',
        'type' => 'qris',
        'provider' => 'BCA',
        'is_active' => true,
        'sort_order' => 10,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/pos/payment-methods');

    $response->assertOk();
    $data = $response->json('data');
    expect(collect($data)->contains(fn ($method) => $method['code'] === 'qris_bca'))->toBeTrue();
});

it('checkout menerima kode payment method yang aktif dari toko', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    $method = PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'QRIS BCA',
        'code' => 'qris_bca',
        'type' => 'qris',
        'provider' => 'BCA',
        'is_active' => true,
        'sort_order' => 10,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $kasir = User::factory()->create(['role_id' => Role::where('name', 'kasir')->first()->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $kasir->id, 'store_id' => $store->id]);
    Sanctum::actingAs($kasir);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'POS-VALIDATION-TEST',
        'status' => 'open',
        'opening_cash' => 500000,
        'opened_at' => now(),
    ]);

    $cart = PosCart::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'customer_id' => null,
        'status' => 'active',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'category_id' => Category::firstOrCreate(['name' => 'Kat 1'], ['status' => 'active', 'created_by' => $owner->id, 'updated_by' => $owner->id])->id,
        'unit_id' => ProductUnit::firstOrCreate(['name' => 'Pcs'], ['status' => 'active', 'created_by' => $owner->id, 'updated_by' => $owner->id])->id,
        'name' => 'Produk test',
        'sku' => 'SKU-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 15000,
        'buy_price' => 12000,
        'initial_stock' => 10,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    PosCartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'barcode' => $product->barcode,
        'unit_name' => 'Pcs',
        'unit_price' => $product->sell_price,
        'quantity' => 1,
        'discount_value' => 0,
        'subtotal' => 15000,
        'source' => 'manual',
    ]);

    $cart->update([
        'subtotal' => 15000,
        'grand_total' => 15000,
    ]);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'device_id' => 'POS-VALIDATION-TEST',
        'cart_id' => $cart->id,
        'idempotency_key' => 'valid-'.uniqid(),
        'payments' => [[
            'payment_method' => $method->code,
            'amount' => 15000,
            'reference_number' => 'REF-123',
        ]],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);
});

it('cashless nonaktif tidak muncul', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Dana',
        'code' => 'dana',
        'type' => 'e_wallet',
        'provider' => 'Dana',
        'is_active' => false,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/pos/payment-methods');

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect(in_array('dana', $codes, true))->toBeFalse();
});

it('cashless store lain tidak muncul', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    $otherOwner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $otherStore = Store::factory()->create(['owner_id' => $otherOwner->id]);
    UserProfile::factory()->create(['user_id' => $otherOwner->id, 'store_id' => $otherStore->id]);

    PaymentMethod::create([
        'store_id' => $otherStore->id,
        'name' => 'QRIS Mandiri',
        'code' => 'qris_mandiri',
        'type' => 'qris',
        'provider' => 'Mandiri',
        'is_active' => true,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/pos/payment-methods');

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect(in_array('qris_mandiri', $codes, true))->toBeFalse();
});
