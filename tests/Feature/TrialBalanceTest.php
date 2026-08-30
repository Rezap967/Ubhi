<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trialBalanceUserWithStore(): array
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

function trialBalanceAccount(int $storeId, string $code, string $name, string $type = 'asset', bool $isActive = true): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        'is_system' => false,
        'is_active' => $isActive,
        'sort_order' => 0,
    ]);
}

function trialBalanceJournal(int $storeId, string $date, string $status, Account $debitAccount, Account $creditAccount, float $amount): JournalEntry
{
    $journal = JournalEntry::query()->create([
        'store_id' => $storeId,
        'journal_no' => 'TB-'.str_replace('-', '', $date).'-'.fake()->unique()->numerify('####'),
        'journal_date' => $date,
        'description' => 'Trial balance test',
        'status' => $status,
        'source_type' => 'manual',
        'total_debit' => $amount,
        'total_credit' => $amount,
    ]);

    $journal->lines()->createMany([
        ['store_id' => $storeId, 'account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
        ['store_id' => $storeId, 'account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
    ]);

    return $journal;
}

test('trial balance only includes posted journals from the active store and period', function () {
    [$user, $store] = trialBalanceUserWithStore();
    $cash = trialBalanceAccount($store->id, '1-1100', 'Kas');
    $capital = trialBalanceAccount($store->id, '3-1100', 'Modal', 'equity');
    $otherStore = Store::factory()->create();
    $otherCash = trialBalanceAccount($otherStore->id, '1-1100', 'Kas Store Lain');

    trialBalanceJournal($store->id, '2026-08-10', 'posted', $cash, $capital, 1000000);
    trialBalanceJournal($store->id, '2026-08-11', 'draft', $cash, $capital, 900000);
    trialBalanceJournal($store->id, '2026-07-31', 'posted', $cash, $capital, 800000);
    trialBalanceJournal($otherStore->id, '2026-08-10', 'posted', $otherCash, $otherCash, 700000);

    $response = $this->actingAs($user)->getJson('/api/akuntansi/trial-balance?month=2026-08&show_zero_balance=false');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.total_mutation_debit', 1000000)
        ->assertJsonPath('data.summary.total_mutation_credit', 1000000)
        ->assertJsonPath('data.summary.difference', 0)
        ->assertJsonPath('data.summary.is_balanced', true)
        ->assertJsonFragment(['code' => '1-1100', 'mutation_debit' => 1000000])
        ->assertJsonFragment(['code' => '3-1100', 'mutation_credit' => 1000000])
        ->assertJsonMissing(['name' => 'Kas Store Lain']);
});

test('trial balance can show zero and inactive accounts and returns empty summary safely', function () {
    [$user, $store] = trialBalanceUserWithStore();
    trialBalanceAccount($store->id, '1-1100', 'Kas');
    trialBalanceAccount($store->id, '1-1200', 'Bank Nonaktif', 'asset', false);

    $this->actingAs($user)
        ->getJson('/api/akuntansi/trial-balance?date_start=2026-08-01&date_end=2026-08-19&show_zero_balance=true&status=inactive')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_mutation_debit', 0)
        ->assertJsonPath('data.summary.total_mutation_credit', 0)
        ->assertJsonPath('data.summary.difference', 0)
        ->assertJsonPath('data.summary.is_balanced', true)
        ->assertJsonFragment(['code' => '1-1200', 'status' => 'inactive', 'status_label' => 'Nonaktif']);
});

test('trial balance accepts legacy frontend sorting filter names', function () {
    [$user] = trialBalanceUserWithStore();

    $this->actingAs($user)
        ->getJson('/api/akuntansi/trial-balance?sort_by=account_code&sort_dir=asc&account_type=all&account_status=all&include_zero_balance=false')
        ->assertSuccessful()
        ->assertJsonPath('data.filters.sort_by', 'code')
        ->assertJsonPath('data.filters.sort_direction', 'asc')
        ->assertJsonPath('data.filters.type', 'all')
        ->assertJsonPath('data.filters.status', 'all')
        ->assertJsonPath('data.filters.show_zero_balance', false);
});

test('trial balance API exposes frontend-compatible account row aliases', function () {
    [$user, $store] = trialBalanceUserWithStore();
    $cash = trialBalanceAccount($store->id, '1-1100', 'Kas');
    $capital = trialBalanceAccount($store->id, '3-1100', 'Modal', 'equity');
    trialBalanceJournal($store->id, '2026-08-01', 'posted', $cash, $capital, 250000);

    $this->actingAs($user)
        ->getJson('/api/akuntansi/trial-balance?month=2026-08')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.account_code', '1-1100')
        ->assertJsonPath('data.items.0.account_name', 'Kas')
        ->assertJsonPath('data.items.0.account_type', 'asset');
});

test('trial balance normalizes slash-formatted dates before querying posted journals', function () {
    [$user, $store] = trialBalanceUserWithStore();
    $cash = trialBalanceAccount($store->id, '1-1100', 'Kas');
    $capital = trialBalanceAccount($store->id, '3-1100', 'Modal', 'equity');
    trialBalanceJournal($store->id, '2026-08-01', 'posted', $cash, $capital, 250000);

    $this->actingAs($user)
        ->getJson('/api/akuntansi/trial-balance?date_start=01/08/2026&date_end=19/08/2026')
        ->assertSuccessful()
        ->assertJsonPath('data.filters.date_start', '2026-08-01')
        ->assertJsonPath('data.filters.date_end', '2026-08-19')
        ->assertJsonPath('data.summary.total_mutation_debit', 250000);
});

test('trial balance returns posted account mutations scoped to the active store', function () {
    [$user, $store] = trialBalanceUserWithStore();
    $cash = trialBalanceAccount($store->id, '1-1100', 'Kas');
    $capital = trialBalanceAccount($store->id, '3-1100', 'Modal', 'equity');
    $otherStore = Store::factory()->create();
    $otherCash = trialBalanceAccount($otherStore->id, '1-1100', 'Kas Store Lain');

    trialBalanceJournal($store->id, '2026-08-01', 'posted', $cash, $capital, 250000);
    trialBalanceJournal($store->id, '2026-08-02', 'draft', $cash, $capital, 900000);
    trialBalanceJournal($otherStore->id, '2026-08-01', 'posted', $otherCash, $otherCash, 700000);

    $this->actingAs($user)
        ->getJson("/akuntansi/neraca-saldo/accounts/{$cash->id}/mutations?date_start=2026-08-01&date_end=2026-08-31")
        ->assertSuccessful()
        ->assertJsonPath('data.account.account_code', '1-1100')
        ->assertJsonPath('data.summary.total_debit', 250000)
        ->assertJsonPath('data.summary.total_credit', 0)
        ->assertJsonPath('data.items.data.0.journal_date', '2026-08-01 00:00:00')
        ->assertJsonPath('data.items.data.0.debit', 250000);
});
