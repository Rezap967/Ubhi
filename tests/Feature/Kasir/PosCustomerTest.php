<?php

use App\Models\Customer\Customer;
use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosSession;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DefaultCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

const POS_CUST_DEVICE = 'POS-CUST-TEST-001';

function posCustomerSeedRoles(): void
{
    foreach (['owner', 'kasir', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function posCustomerMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function posCustomerMakeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function posCustomerOpenSession(User $user, Store $store, string $deviceId = POS_CUST_DEVICE): PosSession
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

function posCustomerGetActiveCart(PosSession $session): PosCart
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

beforeEach(function () {
    posCustomerSeedRoles();

    ['user' => $this->owner, 'store' => $this->store] = posCustomerMakeOwnerWithStore();
    $this->kasir = posCustomerMakeKasir($this->store);

    // Store kedua untuk scope test
    $ownerB = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    $this->otherStore = Store::factory()->create(['owner_id' => $ownerB->id]);
});

// ──────────────────────────────────────────────
// Kelompok 1: Lookup Pelanggan
// ──────────────────────────────────────────────

test('kasir bisa lookup pelanggan aktif', function () {
    Customer::factory()->count(3)->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)->getJson(route('pos.customers.index'));

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data']);

    expect($response->json('data'))->toHaveCount(3);
});

test('owner bisa lookup pelanggan aktif', function () {
    Customer::factory()->count(2)->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->owner)->getJson(route('pos.customers.index'));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('pelanggan nonaktif tidak muncul di lookup', function () {
    Customer::factory()->create(['store_id' => $this->store->id, 'status' => 'aktif']);
    Customer::factory()->create(['store_id' => $this->store->id, 'status' => 'nonaktif']);

    $response = $this->actingAs($this->kasir)->getJson(route('pos.customers.index'));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

test('pelanggan soft-deleted tidak muncul di lookup', function () {
    Customer::factory()->create(['store_id' => $this->store->id, 'status' => 'aktif']);
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->kasir)->getJson(route('pos.customers.index'));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

test('lookup scoped hanya store milik kasir', function () {
    Customer::factory()->create(['store_id' => $this->store->id, 'status' => 'aktif']);
    Customer::factory()->create(['store_id' => $this->otherStore->id, 'status' => 'aktif']);

    $response = $this->actingAs($this->kasir)->getJson(route('pos.customers.index'));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

test('pelanggan UMUM dari seeder muncul paling atas', function () {
    $this->seed(DefaultCustomerSeeder::class);

    // Buat pelanggan reguler – "Ahmad" secara alfabet seharusnya muncul sebelum "Zainal",
    // tapi UMUM (MBR-000000) harus tetap paling atas.
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'code' => 'MBR-000001',
        'name' => 'Ahmad',
        'status' => 'aktif',
    ]);
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'code' => 'MBR-000002',
        'name' => 'Zainal',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)->getJson(route('pos.customers.index'));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->toHaveCount(3)
        ->and($data[0]['code'])->toBe('MBR-000000')
        ->and($data[0]['name'])->toBe('UMUM')
        ->and($data[1]['name'])->toBe('Ahmad');
});

test('lookup bisa filter dengan search', function () {
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'name' => 'Budi Santoso',
        'status' => 'aktif',
    ]);
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'name' => 'Andi Prasetyo',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)
        ->getJson(route('pos.customers.index', ['search' => 'Budi']));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Budi Santoso');
});

// ──────────────────────────────────────────────
// Kelompok 2: Quick Create Pelanggan dari POS
// ──────────────────────────────────────────────

test('kasir bisa quick create pelanggan baru', function () {
    $response = $this->actingAs($this->kasir)
        ->postJson(route('pos.customers.quick-create'), [
            'name' => 'Pelanggan Baru',
            'phone' => '08123456789',
            'email' => 'baru@example.com',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'code', 'name']]);

    expect(Customer::where('name', 'Pelanggan Baru')->where('store_id', $this->store->id)->exists())
        ->toBeTrue();
});

test('owner bisa quick create pelanggan baru', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(route('pos.customers.quick-create'), [
            'name' => 'Pelanggan Owner',
        ]);

    $response->assertStatus(201);
    expect(Customer::where('name', 'Pelanggan Owner')->where('store_id', $this->store->id)->exists())
        ->toBeTrue();
});

test('quick create gagal tanpa nama', function () {
    $response = $this->actingAs($this->kasir)
        ->postJson(route('pos.customers.quick-create'), [
            'phone' => '08123456789',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('quick create menolak field terlarang', function () {
    $response = $this->actingAs($this->kasir)
        ->postJson(route('pos.customers.quick-create'), [
            'name' => 'Test',
            'status' => 'nonaktif',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('quick create menolak nomor telepon duplikat', function () {
    Customer::factory()->create([
        'store_id' => $this->store->id,
        'phone' => '08123456789',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)
        ->postJson(route('pos.customers.quick-create'), [
            'name' => 'Pelanggan Lain',
            'phone' => '08123456789',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

// ──────────────────────────────────────────────
// Kelompok 3: Set Customer ke Cart POS
// ──────────────────────────────────────────────

test('kasir bisa set customer ke cart', function () {
    $session = posCustomerOpenSession($this->kasir, $this->store);
    posCustomerGetActiveCart($session);

    $customer = Customer::factory()->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'device_id' => POS_CUST_DEVICE,
            'customer_id' => $customer->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.customer.name', $customer->name);
});

test('kasir bisa clear customer dari cart dengan null', function () {
    $session = posCustomerOpenSession($this->kasir, $this->store);
    $customer = Customer::factory()->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
    ]);
    posCustomerGetActiveCart($session)->update(['customer_id' => $customer->id]);

    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'device_id' => POS_CUST_DEVICE,
            'customer_id' => null,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.customer', null);
});

test('set customer gagal jika customer dari store lain', function () {
    $session = posCustomerOpenSession($this->kasir, $this->store);
    posCustomerGetActiveCart($session);

    $foreignCustomer = Customer::factory()->create([
        'store_id' => $this->otherStore->id,
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'device_id' => POS_CUST_DEVICE,
            'customer_id' => $foreignCustomer->id,
        ]);

    $response->assertStatus(404);
});

test('set customer gagal jika customer nonaktif', function () {
    $session = posCustomerOpenSession($this->kasir, $this->store);
    posCustomerGetActiveCart($session);

    $nonaktif = Customer::factory()->create([
        'store_id' => $this->store->id,
        'status' => 'nonaktif',
    ]);

    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'device_id' => POS_CUST_DEVICE,
            'customer_id' => $nonaktif->id,
        ]);

    $response->assertStatus(404);
});

test('set customer gagal jika customer soft-deleted', function () {
    $session = posCustomerOpenSession($this->kasir, $this->store);
    posCustomerGetActiveCart($session);

    $deleted = Customer::factory()->create([
        'store_id' => $this->store->id,
        'status' => 'aktif',
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'device_id' => POS_CUST_DEVICE,
            'customer_id' => $deleted->id,
        ]);

    $response->assertStatus(404);
});

test('device_id wajib ada pada patch cart customer', function () {
    $response = $this->actingAs($this->kasir)
        ->patchJson(route('pos.cart.customer.update'), [
            'customer_id' => 1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['device_id']);
});
