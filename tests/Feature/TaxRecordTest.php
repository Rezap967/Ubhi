<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\TaxRecord;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function taxUserWithStore(): array
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

function taxAccount(int $storeId, string $code, string $name): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $name,
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);
}

test('paid tax record creates a posted journal with two lines', function () {
    [$user, $store] = taxUserWithStore();
    $taxAccount = taxAccount($store->id, '2-1100', 'Pajak Dibayar');
    $counterAccount = taxAccount($store->id, '1-1100', 'Kas');

    $this->actingAs($user)->post(route('akuntansi.pajak.store'), [
        'tax_date' => '2026-08-20',
        'tax_type' => 'ppn_keluaran',
        'tax_name' => 'PPN Keluaran',
        'tax_base' => 1000000,
        'tax_rate' => 11,
        'status' => 'paid',
        'tax_account_id' => $taxAccount->id,
        'counter_account_id' => $counterAccount->id,
    ])->assertRedirect(route('akuntansi.pajak.index'));

    $record = TaxRecord::query()->where('store_id', $store->id)->firstOrFail();
    $journal = JournalEntry::query()->where('source_id', $record->id)->firstOrFail();

    expect($record->status)->toBe('paid')
        ->and($record->journal_entry_id)->toBe($journal->id)
        ->and($journal->status)->toBe('posted')
        ->and($journal->source_type)->toBe('tax_record')
        ->and($journal->total_debit)->toBe('110000.00')
        ->and($journal->total_credit)->toBe('110000.00')
        ->and($journal->lines)->toHaveCount(2);
});

test('cancelling a paid tax record creates a posted reversal journal', function () {
    [$user, $store] = taxUserWithStore();
    $taxAccount = taxAccount($store->id, '2-1100', 'Pajak Dibayar');
    $counterAccount = taxAccount($store->id, '1-1100', 'Kas');

    $this->actingAs($user)->post(route('akuntansi.pajak.store'), [
        'tax_date' => '2026-08-20',
        'tax_type' => 'pph_final_umkm',
        'tax_name' => 'PPh Final UMKM',
        'tax_base' => 5000000,
        'tax_rate' => 0.5,
        'status' => 'paid',
        'tax_account_id' => $taxAccount->id,
        'counter_account_id' => $counterAccount->id,
    ])->assertRedirect();

    $record = TaxRecord::query()->where('store_id', $store->id)->firstOrFail();

    $this->actingAs($user)->post(route('akuntansi.pajak.cancel', $record), [
        'cancel_reason' => 'Pembayaran dibatalkan',
    ])->assertRedirect(route('akuntansi.pajak.index'));

    expect($record->fresh()->status)->toBe('cancelled')
        ->and(JournalEntry::query()->where('source_type', 'tax_record_cancel')->where('source_id', $record->id)->where('status', 'posted')->count())
        ->toBe(1);
});

test('pending tax record can be paid through the payment endpoint', function () {
    [$user, $store] = taxUserWithStore();
    $taxAccount = taxAccount($store->id, '2-1100', 'Pajak Dibayar');
    $counterAccount = taxAccount($store->id, '1-1100', 'Kas');

    $this->actingAs($user)->post(route('akuntansi.pajak.store'), [
        'tax_date' => '2026-08-20',
        'tax_type' => 'ppn_keluaran',
        'tax_name' => 'PPN Keluaran',
        'tax_base' => 1000000,
        'tax_rate' => 11,
    ])->assertRedirect();

    $record = TaxRecord::query()->where('store_id', $store->id)->firstOrFail();

    $this->actingAs($user)->post(route('akuntansi.pajak.pay', $record), [
        'tax_account_id' => $taxAccount->id,
        'counter_account_id' => $counterAccount->id,
        'paid_at' => '2026-08-20',
    ])->assertRedirect(route('akuntansi.pajak.index'));

    expect($record->fresh()->status)->toBe('paid')
        ->and($record->fresh()->journal_entry_id)->not->toBeNull();
});

test('tax summary excludes cancelled records and other stores', function () {
    [$user, $store] = taxUserWithStore();
    $otherStore = Store::factory()->create();

    TaxRecord::query()->create([
        'store_id' => $store->id,
        'transaction_no' => 'TAX-SUM-1',
        'tax_date' => '2026-08-20',
        'tax_type' => 'ppn_keluaran',
        'tax_name' => 'PPN Keluaran',
        'tax_base' => 1000000,
        'tax_rate' => 11,
        'tax_amount' => 110000,
        'status' => 'pending',
    ]);
    TaxRecord::query()->create([
        'store_id' => $store->id,
        'transaction_no' => 'TAX-SUM-2',
        'tax_date' => '2026-08-20',
        'tax_type' => 'pph_final_umkm',
        'tax_name' => 'PPh Final UMKM',
        'tax_base' => 5000000,
        'tax_rate' => 0.5,
        'tax_amount' => 25000,
        'status' => 'cancelled',
    ]);
    TaxRecord::query()->create([
        'store_id' => $otherStore->id,
        'transaction_no' => 'TAX-OTHER-1',
        'tax_date' => '2026-08-20',
        'tax_type' => 'ppn_keluaran',
        'tax_name' => 'PPN Toko Lain',
        'tax_base' => 2000000,
        'tax_rate' => 11,
        'tax_amount' => 220000,
        'status' => 'paid',
    ]);

    $this->actingAs($user)
        ->get(route('akuntansi.pajak.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('summary.ppn_amount', 110000)
            ->where('summary.pph_final_amount', 0)
            ->where('summary.total_tax_amount', 110000)
            ->where('total', 2));
});
