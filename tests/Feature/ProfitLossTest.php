<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function profitLossUserWithStore(): array
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

function profitLossAccount(int $storeId, string $code, string $type, string $name): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'normal_balance' => $type === 'revenue' ? 'credit' : 'debit',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);
}

function profitLossJournal(int $storeId, string $date, string $status, Account $revenue, Account $expense, float $amount): JournalEntry
{
    $journal = JournalEntry::query()->create([
        'store_id' => $storeId,
        'journal_no' => 'PL-'.str_replace('-', '', $date).'-'.fake()->unique()->numerify('####'),
        'journal_date' => $date,
        'description' => 'Profit loss test',
        'status' => $status,
        'source_type' => 'manual',
        'total_debit' => $amount,
        'total_credit' => $amount,
    ]);

    $journal->lines()->createMany([
        ['store_id' => $storeId, 'account_id' => $expense->id, 'debit' => $amount, 'credit' => 0, 'line_order' => 0],
        ['store_id' => $storeId, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount, 'line_order' => 1],
    ]);

    return $journal;
}

test('profit loss reads posted revenue and expense journals by period and store', function () {
    [$user, $store] = profitLossUserWithStore();
    $revenue = profitLossAccount($store->id, '4-1100', 'revenue', 'Pendapatan Penjualan');
    $expense = profitLossAccount($store->id, '5-1200', 'expense', 'Beban Operasional');
    $emptyRevenue = profitLossAccount($store->id, '4-1200', 'revenue', 'Pendapatan Lainnya');
    $otherStore = Store::factory()->create();
    $otherRevenue = profitLossAccount($otherStore->id, '4-1100', 'revenue', 'Pendapatan Store Lain');

    profitLossJournal($store->id, '2026-08-05', 'posted', $revenue, $expense, 5000000);
    profitLossJournal($store->id, '2026-08-06', 'draft', $revenue, $expense, 900000);
    profitLossJournal($otherStore->id, '2026-08-05', 'posted', $otherRevenue, $otherRevenue, 700000);

    $this->actingAs($user)
        ->getJson('/api/akuntansi/profit-loss?date_start=01/08/2026&date_end=10/08/2026')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_revenue', 5000000)
        ->assertJsonPath('data.summary.total_expense', 5000000)
        ->assertJsonPath('data.summary.net_profit', 0)
        ->assertJsonPath('data.filters.date_start', '2026-08-01')
        ->assertJsonPath('data.filters.date_end', '2026-08-10')
        ->assertJsonFragment(['code' => '4-1200', 'amount' => 0])
        ->assertJsonMissing(['name' => 'Pendapatan Store Lain']);
});

test('profit loss route returns inertia props with empty report safely', function () {
    [$user] = profitLossUserWithStore();

    $this->actingAs($user)
        ->get('/akuntansi/laba-rugi?date_start=2026-08-01&date_end=2026-08-20')
        ->assertSuccessful()
        ->assertSee('Akuntansi\\/LabaRugi\\/Index');
});

test('profit loss rejects an invalid end date with an end date error', function () {
    [$user] = profitLossUserWithStore();

    $this->actingAs($user)
        ->getJson('/api/akuntansi/profit-loss?date_start=01/08/2026&date_end=invalid-date')
        ->assertUnprocessable()
        ->assertJsonPath('errors.date_end.0', 'Tanggal selesai tidak valid.');
});
