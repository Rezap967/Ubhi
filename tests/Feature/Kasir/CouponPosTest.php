<?php

use App\Models\Jualan\Coupon;
use App\Models\Jualan\CouponCode;
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
use App\Services\Kasir\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function couponPosSeedRoles(): void
{
    foreach (['owner', 'kasir'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function couponPosMakeProduct(Store $store): Product
{
    $category = Category::firstOrCreate(
        ['name' => 'Kategori Test'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id],
    );

    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id],
    );

    return Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'brand_id' => null,
        'unit_id' => $unit->id,
        'name' => 'Produk Test Coupon',
        'description' => 'Produk untuk test coupon POS',
        'sku' => 'COUPON-TEST-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'sell_price' => 100000,
        'buy_price' => 80000,
        'initial_stock' => 100,
        'status' => 'aktif',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ]);
}

function couponPosMakeContext(): array
{
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    $kasirRole = Role::where('name', 'kasir')->first();
    $kasir = User::factory()->create([
        'role_id' => $kasirRole->id,
        'is_active' => true,
    ]);
    UserProfile::factory()->create(['user_id' => $kasir->id, 'store_id' => $store->id]);

    // Create product
    $product = couponPosMakeProduct($store);

    // Open POS session
    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEVICE-001',
        'status' => 'open',
        'opened_at' => now(),
    ]);

    // Create active cart
    $cart = PosCart::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $kasir->id,
        'status' => 'active',
    ]);

    // Add item to cart
    PosCartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'barcode' => $product->barcode,
        'unit_name' => 'Pcs',
        'unit_price' => $product->sell_price,
        'quantity' => 2,
        'subtotal' => $product->sell_price * 2,
    ]);

    $cart->update([
        'subtotal_amount' => $product->sell_price * 2,
        'subtotal' => $product->sell_price * 2,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $product->sell_price * 2,
    ]);

    return [
        'store' => $store,
        'owner' => $owner->fresh(['role', 'profile']),
        'kasir' => $kasir->fresh(['role', 'profile']),
        'product' => $product,
        'session' => $session,
        'cart' => $cart,
    ];
}

beforeEach(function () {
    couponPosSeedRoles();
});

// ─────────────────────────────────────────────────────────────────────────
// COUPON APPLICATION TESTS
// ─────────────────────────────────────────────────────────────────────────

test('kasir can apply valid coupon', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $response = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    if ($response->status() !== 200) {
        $this->fail('Status: '.$response->status().', Body: '.json_encode($response->json()));
    }

    $response->assertOk()
        ->assertJsonPath('data.discount.coupon_id', $coupon->id);

    $this->assertDatabaseHas('pos_applied_discounts', [
        'cart_id' => $context['cart']->id,
        'coupon_id' => $coupon->id,
        'status' => 'active',
    ]);
});

test('cannot apply nonexistent coupon', function () {
    $context = couponPosMakeContext();

    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => 'NOTEXIST',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Kode kupon tidak ditemukan.');
});

test('cannot apply expired coupon', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'status' => 'aktif',
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
    ]);

    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kupon sudah berakhir.');
});

test('cannot apply inactive coupon', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'status' => 'nonaktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kupon tidak aktif.');
});

test('cannot apply coupon below minimum purchase', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => 500000,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable();
});

test('cannot apply coupon when quota exhausted', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'total_quota' => 1,
        'used_count' => 1,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable();
});

test('cannot apply same coupon twice', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'nominal',
        'discount_value' => 50000,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    // Apply first time
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertOk();

    // Apply second time
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kupon sudah digunakan pada keranjang ini.');
});

// ─────────────────────────────────────────────────────────────────────────
// DISCOUNT CALCULATION TESTS
// ─────────────────────────────────────────────────────────────────────────

test('coupon percentage discount calculated correctly', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $response = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    // Refresh the cart to get the recalculated values
    $context['cart']->refresh();
    $expectedDiscount = $context['cart']->subtotal * 0.1;
    $actual = (float) $response->json('data.discount.discount_amount');
    expect($actual)->toBe($expectedDiscount);
});

test('coupon nominal discount applied correctly', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'nominal',
        'discount_value' => 50000,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $response = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    expect($response->json('data.discount.discount_amount'))->toBe(50000.0);
});

test('cart discount is capped to subtotal when coupon or promo exceeds the cart', function () {
    $context = couponPosMakeContext();

    $context['cart']->appliedDiscounts()->create([
        'store_id' => $context['store']->id,
        'coupon_id' => null,
        'promotion_name' => 'Promo Uji',
        'scope' => 'semua_produk',
        'discount_type' => 'potongan_tetap',
        'discount_amount' => 300000,
        'source' => 'manual',
        'status' => 'active',
    ]);

    $updatedCart = app(PricingService::class)->recalculateCart($context['cart']->fresh());

    expect((float) $updatedCart->discount_total)->toBe(200000.0)
        ->and((float) $updatedCart->grand_total)->toBe(0.0);
});

test('coupon discount capped by max discount', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 50,
        'max_discount' => 50000,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $response = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    expect($response->json('data.discount.discount_amount'))->toBe(50000.0);
});

// ─────────────────────────────────────────────────────────────────────────
// DISCOUNT REMOVAL TESTS
// ─────────────────────────────────────────────────────────────────────────

test('kasir can remove applied discount', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    // Apply discount
    $applyResponse = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    $discountId = $applyResponse->json('data.discount.id');

    // Remove discount
    $this->actingAs($context['kasir'])
        ->deleteJson("/api/v1/pos/cart/discounts/{$discountId}", [
            'device_id' => 'DEVICE-001',
        ])
        ->assertOk();

    $this->assertDatabaseHas('pos_applied_discounts', [
        'id' => $discountId,
        'status' => 'removed',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────
// CHECKOUT & COUPON USAGE TESTS
// ─────────────────────────────────────────────────────────────────────────

test('checkout records coupon usage', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    // Apply coupon
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    // Checkout
    $checkoutResponse = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/checkout', [
            'cart_id' => $context['cart']->id,
            'device_id' => 'DEVICE-001',
            'idempotency_key' => 'key-'.now()->timestamp,
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => $context['cart']->grand_total,
                ],
            ],
        ])
        ->assertCreated();

    $transactionId = $checkoutResponse->json('data.transaction_id');

    // Verify coupon_codes entry
    $this->assertDatabaseHas('coupon_codes', [
        'coupon_id' => $coupon->id,
        'pos_transaction_id' => $transactionId,
    ]);

    // Verify used_count incremented
    expect($coupon->fresh()->used_count)->toBe(1);
});

test('checkout idempotency prevents duplicate coupon codes', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    // Apply coupon
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    $idempotencyKey = 'idempotent-key-'.now()->timestamp;

    // Checkout first time
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/checkout', [
            'cart_id' => $context['cart']->id,
            'device_id' => 'DEVICE-001',
            'idempotency_key' => $idempotencyKey,
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => $context['cart']->grand_total,
                ],
            ],
        ])
        ->assertCreated();

    // Checkout second time with same idempotency key
    $secondResponse = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/checkout', [
            'cart_id' => $context['cart']->id,
            'device_id' => 'DEVICE-001',
            'idempotency_key' => $idempotencyKey,
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => $context['cart']->grand_total,
                ],
            ],
        ])
        ->assertOk();

    // Count coupon_codes entries for this coupon - should be 1 (not 2)
    $count = CouponCode::where('coupon_id', $coupon->id)->count();
    expect($count)->toBe(1);
});

test('receipt includes coupon discounts', function () {
    $context = couponPosMakeContext();
    $coupon = Coupon::factory()->for($context['store'])->create([
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'min_purchase' => null,
        'status' => 'aktif',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    // Apply coupon
    $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/cart/discounts/apply-coupon', [
            'device_id' => 'DEVICE-001',
            'coupon_code' => $coupon->code,
        ]);

    // Checkout
    $checkoutResponse = $this->actingAs($context['kasir'])
        ->postJson('/api/v1/pos/checkout', [
            'cart_id' => $context['cart']->id,
            'device_id' => 'DEVICE-001',
            'idempotency_key' => 'key-'.now()->timestamp,
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => $context['cart']->grand_total,
                ],
            ],
        ])
        ->assertCreated();

    $transactionId = $checkoutResponse->json('data.transaction_id');

    // Verify transaction has discount recorded
    expect($checkoutResponse->json('data.discount_total'))->toBeGreaterThan(0);

    // Verify we can fetch receipt
    $receiptResponse = $this->actingAs($context['kasir'])
        ->getJson("/api/v1/pos/transactions/{$transactionId}/receipt");

    $receiptResponse->assertOk();
});
