<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Accounting\JournalEntry;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function expenseUserWithStore(): array
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

function expenseAccount(int $storeId, string $code, string $type): Account
{
    return Account::query()->create([
        'store_id' => $storeId,
        'code' => $code,
        'name' => $code,
        'type' => $type,
        'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);
}

function expenseCategory(int $storeId): ExpenseCategory
{
    return ExpenseCategory::query()->create([
        'store_id' => $storeId,
        'name' => 'Listrik',
        'slug' => 'listrik',
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);
}

function expensePayload(ExpenseCategory $category, Account $expenseAccount, Account $cashAccount, string $status = 'draft'): array
{
    return [
        'expense_date' => '2026-08-20',
        'description' => 'Tagihan listrik toko',
        'category_id' => $category->id,
        'expense_account_id' => $expenseAccount->id,
        'cash_or_bank_account_id' => $cashAccount->id,
        'amount' => 108100,
        'tax_amount' => 0,
        'payment_method' => 'cash',
        'status' => $status,
    ];
}

test('posted expense creates a balanced posted journal', function () {
    [$user, $store] = expenseUserWithStore();
    $category = expenseCategory($store->id);
    $expenseAccount = expenseAccount($store->id, '5-1200', 'expense');
    $cashAccount = expenseAccount($store->id, '1-1100', 'asset');

    $response = $this->actingAs($user)->postJson(route('akuntansi.pengeluaran.store'), expensePayload($category, $expenseAccount, $cashAccount, 'posted'));

    $response->assertCreated()->assertJsonPath('data.status', 'posted');
    $expense = Expense::query()->firstOrFail();
    $journal = JournalEntry::query()->whereKey($expense->journal_entry_id)->firstOrFail();

    expect($expense->expense_no)->toBe('EXP-202608-0001')
        ->and($journal->status)->toBe('posted')
        ->and($journal->source_type)->toBe('expense')
        ->and($journal->total_debit)->toBe('108100.00')
        ->and($journal->lines)->toHaveCount(2);
});

test('draft expense can be updated and deleted', function () {
    [$user, $store] = expenseUserWithStore();
    $category = expenseCategory($store->id);
    $expenseAccount = expenseAccount($store->id, '5-1200', 'expense');
    $cashAccount = expenseAccount($store->id, '1-1100', 'asset');
    $response = $this->actingAs($user)->postJson(route('akuntansi.pengeluaran.store'), expensePayload($category, $expenseAccount, $cashAccount));
    $expense = Expense::query()->firstOrFail();

    $this->actingAs($user)->patchJson(route('akuntansi.pengeluaran.update', $expense), array_merge(expensePayload($category, $expenseAccount, $cashAccount), ['description' => 'Diperbarui']))
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Diperbarui');

    $this->actingAs($user)->deleteJson(route('akuntansi.pengeluaran.destroy', $expense))->assertSuccessful();
    expect(Expense::withTrashed()->find($expense->id)?->deleted_at)->not->toBeNull();
});

test('posted expense can be voided once with a reversal journal', function () {
    [$user, $store] = expenseUserWithStore();
    $category = expenseCategory($store->id);
    $expenseAccount = expenseAccount($store->id, '5-1200', 'expense');
    $cashAccount = expenseAccount($store->id, '1-1100', 'asset');
    $this->actingAs($user)->postJson(route('akuntansi.pengeluaran.store'), expensePayload($category, $expenseAccount, $cashAccount, 'posted'));
    $expense = Expense::query()->firstOrFail();

    $this->actingAs($user)->postJson(route('akuntansi.pengeluaran.void', $expense), ['void_reason' => 'Dibatalkan'])->assertSuccessful();

    expect($expense->fresh()->status)->toBe('void')
        ->and(JournalEntry::query()->where('source_type', 'expense_void')->count())->toBe(1);
});
