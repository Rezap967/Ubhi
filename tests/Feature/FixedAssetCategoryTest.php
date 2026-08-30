<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function fixedCategoryUserWithStore(): array
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

function fixedCategoryAccount(int $storeId, string $code, string $type): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $code,
        'type' => $type,
        'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        'is_active' => true,
        'is_system' => false,
        'sort_order' => 0,
    ]);
}

test('fixed asset category index returns scoped data, account options, and summary', function () {
    [$user, $store] = fixedCategoryUserWithStore();
    $asset = fixedCategoryAccount($store->id, '1-1700', 'asset');
    $accumulated = fixedCategoryAccount($store->id, '1-1790', 'asset');
    $expense = fixedCategoryAccount($store->id, '6-1400', 'expense');
    FixedAssetCategory::query()->create([
        'store_id' => $store->id,
        'code' => 'EQP',
        'name' => 'Peralatan',
        'asset_account_id' => $asset->id,
        'accumulated_depreciation_account_id' => $accumulated->id,
        'depreciation_expense_account_id' => $expense->id,
        'default_useful_life_months' => 48,
        'depreciation_method' => 'straight_line',
        'residual_value_type' => 'none',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('akuntansi.kategori-aset-tetap.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_categories', 1)
        ->assertJsonPath('data.summary.active_categories', 1)
        ->assertJsonPath('data.items.0.category_code', 'EQP')
        ->assertJsonPath('data.items.0.asset_account.account_code', '1-1700');
});

test('fixed asset category can be created and status can be changed', function () {
    [$user, $store] = fixedCategoryUserWithStore();
    $asset = fixedCategoryAccount($store->id, '1-1700', 'asset');
    $accumulated = fixedCategoryAccount($store->id, '1-1790', 'asset');
    $expense = fixedCategoryAccount($store->id, '6-1400', 'expense');

    $this->actingAs($user)->postJson(route('akuntansi.kategori-aset-tetap.store'), [
        'code' => 'EQP',
        'name' => 'Peralatan',
        'asset_account_id' => $asset->id,
        'accumulated_depreciation_account_id' => $accumulated->id,
        'depreciation_expense_account_id' => $expense->id,
        'default_useful_life_months' => 48,
        'depreciation_method' => 'straight_line',
        'residual_value_type' => 'none',
        'is_active' => true,
    ])->assertSuccessful();

    $category = FixedAssetCategory::query()->where('store_id', $store->id)->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('akuntansi.kategori-aset-tetap.update', $category), ['is_active' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);
});

test('fixed asset category code is unique per store and cross-store data is hidden', function () {
    [$user, $store] = fixedCategoryUserWithStore();
    $otherStore = Store::factory()->create();
    $asset = fixedCategoryAccount($store->id, '1-1700', 'asset');
    $accumulated = fixedCategoryAccount($store->id, '1-1790', 'asset');
    $expense = fixedCategoryAccount($store->id, '6-1400', 'expense');
    $otherAsset = fixedCategoryAccount($otherStore->id, '1-1700', 'asset');
    $otherAccumulated = fixedCategoryAccount($otherStore->id, '1-1790', 'asset');
    $otherExpense = fixedCategoryAccount($otherStore->id, '6-1400', 'expense');

    FixedAssetCategory::query()->create(['store_id' => $store->id, 'code' => 'EQP', 'name' => 'Peralatan', 'asset_account_id' => $asset->id, 'accumulated_depreciation_account_id' => $accumulated->id, 'depreciation_expense_account_id' => $expense->id, 'depreciation_method' => 'straight_line', 'default_useful_life_months' => 48, 'residual_value_type' => 'none', 'is_active' => true]);
    FixedAssetCategory::query()->create(['store_id' => $otherStore->id, 'code' => 'OTH', 'name' => 'Toko Lain', 'asset_account_id' => $otherAsset->id, 'accumulated_depreciation_account_id' => $otherAccumulated->id, 'depreciation_expense_account_id' => $otherExpense->id, 'depreciation_method' => 'straight_line', 'default_useful_life_months' => 48, 'residual_value_type' => 'none', 'is_active' => true]);

    $this->actingAs($user)
        ->getJson(route('akuntansi.kategori-aset-tetap.index'))
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.code', 'EQP');
});
