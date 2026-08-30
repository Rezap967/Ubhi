<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\CustomerReceivablePayment;
use App\Models\Accounting\JournalEntry;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function receivableUserWithStore(): array
{
    $role = Role::query()->firstOrCreate(['name' => 'owner']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::query()->create(['user_id' => $user->id, 'store_id' => $store->id, 'full_name' => $user->name]);

    return [$user, $store];
}

function receivableAccount(int $storeId, string $code, string $name, string $type): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);
}

test('customer receivable recognition and payment create balanced posted journals', function () {
    [$user, $store] = receivableUserWithStore();
    $receivableAccount = receivableAccount($store->id, '1-1300', 'Piutang Pelanggan', 'asset');
    $revenueAccount = receivableAccount($store->id, '4-1000', 'Pendapatan Penjualan', 'revenue');
    $cashAccount = receivableAccount($store->id, '1-1100', 'Kas', 'asset');

    $this->actingAs($user)->post(route('akuntansi.piutang-pelanggan.store'), [
        'customer_name' => 'Pelanggan Uji',
        'receivable_date' => '2026-08-20',
        'due_date' => '2026-08-30',
        'description' => 'Penjualan tempo',
        'original_amount' => 7000,
        'receivable_account_id' => $receivableAccount->id,
        'revenue_account_id' => $revenueAccount->id,
    ])->assertRedirect();

    $receivable = CustomerReceivable::query()->firstOrFail();
    $recognition = JournalEntry::query()->where('source_type', 'customer_receivable')->firstOrFail();

    expect($receivable->outstanding_amount)->toBe('7000.00')
        ->and($receivable->status)->toBe('open')
        ->and($recognition->status)->toBe('posted')
        ->and($recognition->total_debit)->toBe('7000.00')
        ->and($recognition->total_credit)->toBe('7000.00');

    $this->actingAs($user)->post(route('akuntansi.piutang-pelanggan.payments', $receivable), [
        'payment_date' => '2026-08-20',
        'amount' => 2000,
        'cash_or_bank_account_id' => $cashAccount->id,
        'payment_method' => 'cash',
    ])->assertRedirect();

    $payment = CustomerReceivablePayment::query()->firstOrFail();
    $paymentJournal = JournalEntry::query()->where('source_type', 'customer_receivable_payment')->firstOrFail();
    $receivable->refresh();

    expect($payment->journal_entry_id)->toBe($paymentJournal->id)
        ->and($receivable->paid_amount)->toBe('2000.00')
        ->and($receivable->outstanding_amount)->toBe('5000.00')
        ->and($receivable->status)->toBe('partial')
        ->and($paymentJournal->total_debit)->toBe('2000.00')
        ->and($paymentJournal->total_credit)->toBe('2000.00');
});

test('posted customer receivable can be voided with a balanced reversal journal', function () {
    [$user, $store] = receivableUserWithStore();
    $receivableAccount = receivableAccount($store->id, '1-1300', 'Piutang Pelanggan', 'asset');
    $revenueAccount = receivableAccount($store->id, '4-1000', 'Pendapatan Penjualan', 'revenue');

    $this->actingAs($user)->post(route('akuntansi.piutang-pelanggan.store'), [
        'customer_name' => 'Pelanggan Void',
        'receivable_date' => '2026-08-20',
        'due_date' => '2026-08-30',
        'original_amount' => 9000,
        'receivable_account_id' => $receivableAccount->id,
        'revenue_account_id' => $revenueAccount->id,
    ])->assertRedirect();

    $receivable = CustomerReceivable::query()->firstOrFail();

    $this->actingAs($user)->post(route('akuntansi.piutang-pelanggan.void', $receivable), [
        'void_reason' => 'Transaksi dibatalkan',
    ])->assertRedirect();

    $reversal = JournalEntry::query()->where('source_type', 'customer_receivable_void')->firstOrFail();

    expect($receivable->refresh()->status)->toBe('void')
        ->and($reversal->status)->toBe('posted')
        ->and($reversal->total_debit)->toBe('9000.00')
        ->and($reversal->total_credit)->toBe('9000.00')
        ->and($reversal->lines)->toHaveCount(2);
});
