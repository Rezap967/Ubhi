<?php

use App\Models\Role;
use App\Models\Settings\PaymentMethod;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedPaymentMethodRoles(): void
{
    foreach (['owner', 'kasir', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

beforeEach(function () {
    seedPaymentMethodRoles();
});

it('owner bisa create payment method', function () {
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/settings/payment-methods', [
        'name' => 'QRIS BCA',
        'code' => 'qris_bca',
        'type' => 'qris',
        'provider' => 'BCA',
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'QRIS BCA');
});

it('create payment method otomatis menghasilkan code dari nama', function () {
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/settings/payment-methods', [
        'name' => 'QRIS BCA',
        'type' => 'qris',
        'provider' => 'BCA',
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'qris_bca');
});

it('manajer bisa create payment method', function () {
    $role = Role::where('name', 'manajer')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/settings/payment-methods', [
        'name' => 'OVO',
        'code' => 'ovo',
        'type' => 'e_wallet',
        'provider' => 'OVO',
        'is_active' => true,
    ]);

    $response->assertCreated()->assertJsonPath('success', true);
});

it('kasir tidak bisa create payment method', function () {
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/settings/payment-methods', [
        'name' => 'Dana',
        'code' => 'dana',
        'type' => 'e_wallet',
        'provider' => 'Dana',
        'is_active' => true,
    ]);

    $response->assertStatus(403);
});

it('code unique per store', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'QRIS BCA',
        'code' => 'qris_bca',
        'type' => 'qris',
        'is_active' => true,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/settings/payment-methods', [
        'name' => 'QRIS BCA Baru',
        'code' => 'qris_bca',
        'type' => 'qris',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
});

it('store scope aman', function () {
    $ownerRole = Role::where('name', 'owner')->first();
    $owner1 = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store1 = Store::factory()->create(['owner_id' => $owner1->id]);
    UserProfile::factory()->create(['user_id' => $owner1->id, 'store_id' => $store1->id]);

    $owner2 = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);
    $store2 = Store::factory()->create(['owner_id' => $owner2->id]);
    UserProfile::factory()->create(['user_id' => $owner2->id, 'store_id' => $store2->id]);

    $method = PaymentMethod::create([
        'store_id' => $store2->id,
        'name' => 'Dana',
        'code' => 'dana_store2',
        'type' => 'e_wallet',
        'is_active' => true,
        'created_by' => $owner2->id,
        'updated_by' => $owner2->id,
    ]);

    Sanctum::actingAs($owner1);

    $response = $this->getJson('/api/v1/settings/payment-methods/'.$method->id);

    $response->assertStatus(404);
});

it('toggle active nonactive', function () {
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $method = PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'GoPay',
        'code' => 'gopay',
        'type' => 'e_wallet',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/v1/settings/payment-methods/'.$method->id.'/toggle');

    $response->assertOk()->assertJsonPath('data.is_active', false);
});

it('delete soft delete', function () {
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $method = PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'BCA VA',
        'code' => 'bca_va',
        'type' => 'bank_transfer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/settings/payment-methods/'.$method->id);

    $response->assertOk();
    $this->assertSoftDeleted($method);
});
