<?php

use App\Models\Accounting\TaxRecord;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function estimationUserWithStore(): array
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

function estimationRecord(int $storeId, string $transactionNo, string $type, float $base, float $rate, float $amount, string $status, string $date = '2026-08-20'): TaxRecord
{
    return TaxRecord::query()->create([
        'store_id' => $storeId,
        'transaction_no' => $transactionNo,
        'tax_date' => $date,
        'tax_type' => $type,
        'tax_name' => $type,
        'tax_base' => $base,
        'tax_rate' => $rate,
        'tax_amount' => $amount,
        'status' => $status,
    ]);
}

test('tax estimation groups active records and excludes cancelled records by default', function () {
    [$user, $store] = estimationUserWithStore();
    estimationRecord($store->id, 'EST-1', 'ppn_keluaran', 1000000, 11, 110000, 'pending');
    estimationRecord($store->id, 'EST-2', 'ppn_masukan', 500000, 11, 55000, 'paid');
    estimationRecord($store->id, 'EST-3', 'pph_final_umkm', 5000000, 0.5, 25000, 'cancelled');
    estimationRecord($store->id, 'EST-OTHER', 'ppn_keluaran', 2000000, 11, 220000, 'paid', '2026-07-20');

    $this->actingAs($user)
        ->getJson('/api/akuntansi/tax-estimation?month=2026-08')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.ppn_amount', 165000)
        ->assertJsonPath('data.summary.ppn_base', 1500000)
        ->assertJsonPath('data.summary.ppn_count', 2)
        ->assertJsonPath('data.summary.pph_final_amount', 0)
        ->assertJsonPath('data.summary.total_tax_amount', 165000)
        ->assertJsonPath('data.summary.total_transaction_count', 2)
        ->assertJsonPath('data.rows.data.0.tax_type', 'ppn_keluaran')
        ->assertJsonPath('data.rows.data.0.transaction_count', 1);
});

test('tax estimation status filter can include cancelled records', function () {
    [$user, $store] = estimationUserWithStore();
    estimationRecord($store->id, 'EST-CANCELLED', 'pph_final_umkm', 5000000, 0.5, 25000, 'cancelled');

    $this->actingAs($user)
        ->getJson('/api/akuntansi/tax-estimation?status=cancelled')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.pph_final_amount', 25000)
        ->assertJsonPath('data.summary.total_transaction_count', 1)
        ->assertJsonPath('data.rows.data.0.tax_type_label', 'PPh Final UMKM');
});

test('tax estimation is scoped to the active store and supports date range filters', function () {
    [$user, $store] = estimationUserWithStore();
    $otherStore = Store::factory()->create();
    estimationRecord($store->id, 'EST-IN-RANGE', 'other', 100000, 2, 2000, 'pending', '2026-08-20');
    estimationRecord($store->id, 'EST-OUT-RANGE', 'other', 200000, 2, 4000, 'pending', '2026-07-20');
    estimationRecord($otherStore->id, 'EST-OTHER-STORE', 'other', 900000, 2, 18000, 'pending', '2026-08-20');

    $this->actingAs($user)
        ->getJson('/api/akuntansi/tax-estimation?date_start=20/08/2026&date_end=20/08/2026')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_tax_amount', 2000)
        ->assertJsonPath('data.summary.total_tax_base', 100000)
        ->assertJsonPath('data.summary.total_transaction_count', 1);
});

test('tax estimation returns zero summary when no record matches', function () {
    [$user] = estimationUserWithStore();

    $this->actingAs($user)
        ->getJson('/api/akuntansi/tax-estimation?month=2025-01')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_tax_amount', 0)
        ->assertJsonPath('data.summary.total_transaction_count', 0)
        ->assertJsonCount(0, 'data.rows.data');
});
