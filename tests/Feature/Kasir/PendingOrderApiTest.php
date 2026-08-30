<?php

use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['owner', 'kasir', 'manajer'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName]);
    }
});

it('kasir dapat melihat daftar pesanan tersimpan', function () {
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    DB::table('pending_orders')->insert([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'name' => 'Meja 1',
        'customer_name' => 'Budi',
        'note' => 'Tanpa es batu',
        'items' => json_encode([
            ['product_id' => 1, 'quantity' => 2],
        ]),
        'subtotal' => 20000,
        'tax' => 0,
        'total' => 20000,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/pending-orders');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                ['id', 'name', 'customer_name', 'items', 'subtotal', 'tax', 'total'],
            ],
        ]);
});

it('kasir dapat menyimpan pesanan baru', function () {
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/pending-orders', [
        'name' => 'Meja 5',
        'customer_name' => 'Siti',
        'note' => 'Minta ditulis',
        'items' => [
            ['product_id' => 10, 'quantity' => 2, 'name' => 'Es Teh'],
        ],
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('pending_orders', ['name' => 'Meja 5', 'status' => 'pending']);
});

it('kasir dapat menghapus pesanan tersimpan', function () {
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $id = DB::table('pending_orders')->insertGetId([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'name' => 'Meja 7',
        'customer_name' => 'Aji',
        'note' => '',
        'items' => json_encode([
            ['product_id' => 7, 'quantity' => 1],
        ]),
        'subtotal' => 15000,
        'tax' => 0,
        'total' => 15000,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/pending-orders/{$id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('pending_orders', ['id' => $id]);
});

it('items dihapus dengan benar ketika decode dari JSON', function () {
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
    ]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $items = [
        ['product_id' => 1, 'quantity' => 2, 'name' => 'Kopi', 'price' => 10000, 'subtotal' => 20000],
        ['product_id' => 2, 'quantity' => 1, 'name' => 'Teh', 'price' => 8000, 'subtotal' => 8000],
    ];

    DB::table('pending_orders')->insert([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'name' => 'Meja 3',
        'customer_name' => null,
        'note' => null,
        'items' => json_encode($items),
        'subtotal' => 28000,
        'tax' => 0,
        'total' => 28000,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/pending-orders');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.items', $items);
});
