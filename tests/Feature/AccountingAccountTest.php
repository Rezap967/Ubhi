<?php

use App\Models\Accounting\Account;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accountingUserWithStore(): array
{
    $role = Role::query()->firstOrCreate(['name' => 'owner']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $store = Store::factory()->create(['owner_id' => $user->id]);

    UserProfile::query()->create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'full_name' => $user->name,
    ]);

    return [$user, $store];
}

function makeAccountingAccount(array $attributes): Account
{
    return Account::query()->create(array_merge([
        'code' => fake()->unique()->bothify('A-####'),
        'name' => 'Akun Test',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ], $attributes));
}

test('owner can create an account with an automatic normal balance', function () {
    [$user, $store] = accountingUserWithStore();

    $response = $this->actingAs($user)->post('/akuntansi/bagan-akun', [
        'code' => '6-1100',
        'name' => 'Beban Operasional Toko',
        'type' => 'expense',
    ]);

    $response->assertRedirect(route('accounting.accounts.index'));

    $this->assertDatabaseHas('accounts', [
        'store_id' => $store->id,
        'code' => '6-1100',
        'type' => 'expense',
        'normal_balance' => 'debit',
        'is_system' => false,
        'is_active' => true,
    ]);
});

test('account autocomplete only returns active accounts from the active store', function () {
    [$user, $store] = accountingUserWithStore();
    $otherStore = Store::factory()->create();

    makeAccountingAccount([
        'store_id' => $store->id,
        'code' => '1-1100',
        'name' => 'Kas Toko',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    makeAccountingAccount([
        'store_id' => $store->id,
        'code' => '1-1200',
        'name' => 'Bank Nonaktif',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => false,
    ]);
    makeAccountingAccount([
        'store_id' => $otherStore->id,
        'code' => '1-1300',
        'name' => 'Kas Store Lain',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    $deleted = makeAccountingAccount([
        'store_id' => $store->id,
        'code' => '1-1400',
        'name' => 'Kas Terhapus',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    $deleted->delete();

    $response = $this->actingAs($user)->getJson('/api/akuntansi/accounts/search?q=Kas');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account_code', '1-1100')
        ->assertJsonPath('data.0.account_name', 'Kas Toko')
        ->assertJsonPath('data.0.account_type', 'asset')
        ->assertJsonPath('data.0.normal_balance', 'debit')
        ->assertJsonPath('data.0.label', '1-1100 - Kas Toko');
});

test('system accounts and accounts with active children cannot be deactivated', function () {
    [$user, $store] = accountingUserWithStore();
    $system = makeAccountingAccount([
        'store_id' => $store->id,
        'is_system' => true,
        'is_active' => true,
    ]);
    $parent = makeAccountingAccount([
        'store_id' => $store->id,
        'is_system' => false,
        'is_active' => true,
        'type' => 'asset',
        'normal_balance' => 'debit',
    ]);
    makeAccountingAccount([
        'store_id' => $store->id,
        'parent_id' => $parent->id,
        'is_active' => true,
        'type' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $this->actingAs($user)->delete(route('accounting.accounts.destroy', $system))
        ->assertStatus(409);
    $this->actingAs($user)->delete(route('accounting.accounts.destroy', $parent))
        ->assertStatus(409);

    expect($system->fresh()->is_active)->toBeTrue()
        ->and($parent->fresh()->is_active)->toBeTrue();
});
