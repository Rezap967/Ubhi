<?php

use App\Models\Jualan\Promotion;
use App\Models\Jualan\PromotionTarget;
use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosSession;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\Jualan\Promotions\PromotionEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function promotionSeedRoles(): void
{
    foreach (['owner', 'manajer', 'kasir', 'petugas_gudang'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName]);
    }
}

function promotionMakeOwnerWithStore(): array
{
    promotionSeedRoles();

    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    return ['owner' => $owner->fresh(['role', 'profile']), 'store' => $store];
}

function promotionMakeManagerWithStore(Store $store): User
{
    promotionSeedRoles();

    $role = Role::where('name', 'manajer')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function promotionMakeKasir(Store $store): User
{
    promotionSeedRoles();

    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function promotionMakeProduct(Store $store, array $overrides = []): Product
{
    $category = Category::firstOrCreate(
        ['name' => 'Kategori Promo'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id],
    );

    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id],
    );

    return Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'brand_id' => null,
        'unit_id' => $unit->id,
        'name' => 'Produk Promo Test',
        'description' => 'Produk untuk promo',
        'sku' => 'PROMO-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'sell_price' => 50000,
        'buy_price' => 40000,
        'initial_stock' => 100,
        'status' => 'aktif',
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $overrides));
}

beforeEach(function () {
    promotionSeedRoles();
});

it('owner bisa melihat daftar promosi', function () {
    ['owner' => $owner, 'store' => $store] = promotionMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    Promotion::factory()->create([
        'store_id' => $store->id,
        'name' => 'Promo Owner List',
        'type' => 'diskon_persen',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'scope' => 'semua_produk',
        'status' => 'aktif',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('manajer bisa melihat daftar promosi', function () {
    ['store' => $store] = promotionMakeOwnerWithStore();
    $manager = promotionMakeManagerWithStore($store);
    Sanctum::actingAs($manager);

    Promotion::factory()->create([
        'store_id' => $store->id,
        'name' => 'Promo Manager',
        'type' => 'potongan_tetap',
        'discount_value' => 25000,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'scope' => 'semua_produk',
        'status' => 'aktif',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $this->getJson('/api/v1/promotions')
        ->assertOk();
});

it('kasir tidak bisa crud promosi', function () {
    ['store' => $store] = promotionMakeOwnerWithStore();
    $kasir = promotionMakeKasir($store);
    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/promotions')->assertStatus(403);
    $this->postJson('/api/v1/promotions', [
        'name' => 'Promo Kasir',
        'type' => 'diskon_persen',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'scope' => 'semua_produk',
        'status' => 'aktif',
    ])->assertStatus(403);
});

it('owner bisa membuat promosi scope semua_produk', function () {
    ['owner' => $owner, 'store' => $store] = promotionMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/promotions', [
        'name' => 'Diskon All Product',
        'description' => 'Promo semua produk',
        'type' => 'diskon_persen',
        'discount_value' => 10,
        'max_discount' => 50000,
        'min_purchase' => 100000,
        'min_qty' => null,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(15)->toDateString(),
        'scope' => 'semua_produk',
        'max_usage' => null,
        'per_customer_limit' => null,
        'is_stackable' => false,
        'priority' => 10,
        'status' => 'aktif',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('promotions', [
        'store_id' => $store->id,
        'name' => 'Diskon All Product',
        'type' => 'diskon_persen',
        'scope' => 'semua_produk',
    ]);
});

it('owner bisa membuat promosi scope per_kategori dengan targets', function () {
    ['owner' => $owner, 'store' => $store] = promotionMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $category = Category::firstOrCreate(
        ['name' => 'Kategori Promo Target'],
        ['status' => 'active', 'created_by' => $owner->id, 'updated_by' => $owner->id],
    );

    $response = $this->postJson('/api/v1/promotions', [
        'name' => 'Promo Kategori',
        'type' => 'diskon_persen',
        'discount_value' => 15,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'scope' => 'per_kategori',
        'status' => 'aktif',
        'priority' => 20,
        'targets' => [
            ['target_type' => 'kategori', 'target_id' => $category->id],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $promotion = Promotion::query()->where('name', 'Promo Kategori')->first();
    expect($promotion)->not->toBeNull();
    expect(PromotionTarget::where('promotion_id', $promotion->id)->count())->toBe(1);
});

it('scope bukan semua_produk tanpa targets ditolak', function () {
    ['owner' => $owner, 'store' => $store] = promotionMakeOwnerWithStore();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/promotions', [
        'name' => 'Promo tanpa target',
        'type' => 'diskon_persen',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'scope' => 'per_produk',
        'status' => 'aktif',
    ])->assertStatus(422);
});

it('promotion eligibility service menemukan promo eligible', function () {
    ['owner' => $owner, 'store' => $store] = promotionMakeOwnerWithStore();
    $service = app(PromotionEligibilityService::class);

    $product = promotionMakeProduct($store, ['category_id' => 1, 'sell_price' => 100000]);

    Promotion::factory()->create([
        'store_id' => $store->id,
        'name' => 'Promo eligible semua produk',
        'type' => 'diskon_persen',
        'discount_value' => 10,
        'min_purchase' => 50000,
        'start_date' => now()->subDays(1)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'scope' => 'semua_produk',
        'status' => 'aktif',
        'priority' => 10,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'device_id' => 'PROMO-TEST-DEVICE',
        'status' => 'open',
        'opening_cash' => 500000,
        'opened_at' => now(),
    ]);

    $cart = PosCart::create([
        'store_id' => $store->id,
        'session_id' => $session->id,
        'user_id' => $owner->id,
        'status' => 'active',
        'subtotal' => 100000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 100000,
    ]);

    $eligible = $service->getEligiblePromotionsForCart($cart);
    expect($eligible->count())->toBeGreaterThanOrEqual(1);
});
