<?php

use App\Models\Customer\CustomerGroup;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'owner']);
    Role::firstOrCreate(['name' => 'kasir']);
    Role::firstOrCreate(['name' => 'petugas_gudang']);
});

it('returns paginated customer groups in the expected data array shape', function () {
    $ownerRole = Role::where('name', 'owner')->first();

    $user = User::factory()->create([
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create([
        'owner_id' => $user->id,
    ]);

    UserProfile::factory()->create([
        'user_id' => $user->id,
        'store_id' => $store->id,
    ]);

    CustomerGroup::create([
        'store_id' => $store->id,
        'name' => 'VIP',
        'description' => 'Grup VIP pelanggan',
        'discount_type' => 'persen',
        'discount_value' => 10,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/customers/groups?page=1&per_page=50');

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'data' => [
                ['id', 'name', 'description', 'discount_type', 'discount_value'],
            ],
        ],
    ]);
    $response->assertJsonPath('data.data.0.name', 'VIP');
});
