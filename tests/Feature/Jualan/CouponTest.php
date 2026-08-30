<?php

use App\Models\Jualan\Coupon;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function couponSeedRoles(): void
{
    foreach (['owner', 'manajer', 'kasir'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function couponMakeStoreWithUsers(): array
{
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    $manajerRole = Role::where('name', 'manajer')->first();
    $manajer = User::factory()->create([
        'role_id' => $manajerRole->id,
        'is_active' => true,
    ]);
    UserProfile::factory()->create(['user_id' => $manajer->id, 'store_id' => $store->id]);

    $kasirRole = Role::where('name', 'kasir')->first();
    $kasir = User::factory()->create([
        'role_id' => $kasirRole->id,
        'is_active' => true,
    ]);
    UserProfile::factory()->create(['user_id' => $kasir->id, 'store_id' => $store->id]);

    return [
        'store' => $store,
        'owner' => $owner->fresh(['role', 'profile']),
        'manajer' => $manajer->fresh(['role', 'profile']),
        'kasir' => $kasir->fresh(['role', 'profile']),
    ];
}

beforeEach(function () {
    couponSeedRoles();
});

// ─────────────────────────────────────────────────────────────────────────
// AUTHORIZATION TESTS
// ─────────────────────────────────────────────────────────────────────────

test('kasir cannot view coupons', function () {
    $users = couponMakeStoreWithUsers();

    $this->actingAs($users['kasir'])
        ->getJson('/api/v1/coupons')
        ->assertForbidden();
});

test('owner can view coupons', function () {
    $users = couponMakeStoreWithUsers();
    Coupon::factory()->for($users['store'])->create();

    $this->actingAs($users['owner'])
        ->getJson('/api/v1/coupons')
        ->assertOk()
        ->assertJsonStructure([
            'success', 'message', 'data', 'meta',
        ]);
});

test('manajer can view coupons', function () {
    $users = couponMakeStoreWithUsers();
    Coupon::factory()->for($users['store'])->create();

    $this->actingAs($users['manajer'])
        ->getJson('/api/v1/coupons')
        ->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────
// CRUD TESTS
// ─────────────────────────────────────────────────────────────────────────

test('owner can create coupon', function () {
    $users = couponMakeStoreWithUsers();

    $payload = [
        'code' => 'DISKON50',
        'name' => 'Diskon 50%',
        'discount_type' => 'persentase',
        'discount_value' => 50,
        'max_discount' => 100000,
        'min_purchase' => 50000,
        'description' => 'Diskon spesial',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'total_quota' => 100,
        'per_customer_limit' => 1,
        'status' => 'aktif',
    ];

    $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons', $payload)
        ->assertCreated()
        ->assertJsonPath('data.code', 'DISKON50');

    $this->assertDatabaseHas('coupons', [
        'store_id' => $users['store']->id,
        'code' => 'DISKON50',
    ]);
});

test('coupon code must be unique per store', function () {
    $users = couponMakeStoreWithUsers();
    Coupon::factory()->for($users['store'])->create(['code' => 'DUPLICATE']);

    $payload = [
        'code' => 'DUPLICATE',
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ];

    $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Kode kupon sudah ada.');
});

test('discount value validation rejects over 100%', function () {
    $users = couponMakeStoreWithUsers();

    $payload = [
        'code' => 'INVALID',
        'discount_type' => 'persentase',
        'discount_value' => 150,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ];

    $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.discount_value.0', 'Diskon persentase tidak boleh melebihi 100%.');
});

test('owner can update coupon', function () {
    $users = couponMakeStoreWithUsers();
    $coupon = Coupon::factory()->for($users['store'])->create(['status' => 'aktif']);

    $this->actingAs($users['owner'])
        ->putJson("/api/v1/coupons/{$coupon->id}", [
            'code' => $coupon->code,
            'status' => 'nonaktif',
            'name' => 'Updated Name',
        ])
        ->assertOk();

    $this->assertEquals('nonaktif', $coupon->fresh()->status);
});

test('cannot update discount type after usage', function () {
    $users = couponMakeStoreWithUsers();
    $coupon = Coupon::factory()->for($users['store'])->create([
        'discount_type' => 'persentase',
        'used_count' => 1,
    ]);

    $this->actingAs($users['owner'])
        ->putJson("/api/v1/coupons/{$coupon->id}", [
            'code' => $coupon->code,
            'discount_type' => 'nominal',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.discount_type.0', 'Tidak bisa mengubah discount_type karena kupon sudah digunakan.');
});

test('owner can delete coupon', function () {
    $users = couponMakeStoreWithUsers();
    $coupon = Coupon::factory()->for($users['store'])->create();

    $this->actingAs($users['owner'])
        ->deleteJson("/api/v1/coupons/{$coupon->id}")
        ->assertOk();

    $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
});

// ─────────────────────────────────────────────────────────────────────────
// GENERATE TESTS
// ─────────────────────────────────────────────────────────────────────────

test('owner can generate bulk coupons', function () {
    $users = couponMakeStoreWithUsers();

    $payload = [
        'code_prefix' => 'BDAY',
        'name_prefix' => 'Birthday Promo',
        'discount_type' => 'persentase',
        'discount_value' => 15,
        'quantity' => 5,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ];

    $response = $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons/generate', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.generated_count', 5);

    $this->assertIsArray($response->json('data.codes'));
    $this->assertCount(5, $response->json('data.codes'));

    $this->assertDatabaseCount('coupons', 5);
});

test('generated coupons have unique codes', function () {
    $users = couponMakeStoreWithUsers();

    $payload = [
        'code_prefix' => 'PROMO',
        'quantity' => 10,
        'discount_type' => 'nominal',
        'discount_value' => 50000,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ];

    $response = $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons/generate', $payload);

    $codes = $response->json('data.codes');
    expect(count($codes))->toBe(10);
    expect(count(array_unique($codes)))->toBe(10);
});

test('cannot generate more than 500 coupons', function () {
    $users = couponMakeStoreWithUsers();

    $payload = [
        'code_prefix' => 'TOO_MANY',
        'quantity' => 501,
        'discount_type' => 'persentase',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ];

    $this->actingAs($users['owner'])
        ->postJson('/api/v1/coupons/generate', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.quantity.0', 'Jumlah kupon harus antara 1 hingga 500.');
});
