<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function journalUserWithStore(): array
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

function journalAccount(int $storeId, string $code, string $name): Account
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

test('manual journal can be created and posted with automatic journal number', function () {
    [$user, $store] = journalUserWithStore();
    $cash = journalAccount($store->id, '1-1100', 'Kas');
    $capital = journalAccount($store->id, '3-1100', 'Modal Pemilik');

    $response = $this->from('/UbhiSir')->actingAs($user)->post(route('akuntansi.jurnal-umum.store'), [
        'journal_date' => '2026-08-19',
        'description' => 'Setoran modal awal',
        'status' => 'posted',
        'lines' => [
            ['account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000000],
        ],
    ]);

    $response->assertRedirect('/UbhiSir');

    $journal = JournalEntry::query()->where('store_id', $store->id)->firstOrFail();

    expect($journal->journal_no)->toBe('JU-202608-0001')
        ->and($journal->status)->toBe('posted')
        ->and($journal->lines)->toHaveCount(2);

    expect($journal->source_type)->toBe('manual')
        ->and($journal->lines->pluck('account_id')->all())
        ->toEqual([$cash->id, $capital->id]);
});

test('unbalanced journal is rejected', function () {
    [$user, $store] = journalUserWithStore();
    $cash = journalAccount($store->id, '1-1100', 'Kas');
    $capital = journalAccount($store->id, '3-1100', 'Modal Pemilik');

    $response = $this->actingAs($user)->post(route('akuntansi.jurnal-umum.store'), [
        'journal_date' => '2026-08-19',
        'description' => 'Jurnal tidak balance',
        'lines' => [
            ['account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 500000],
        ],
    ]);

    $response->assertSessionHasErrors('lines');
    expect(JournalEntry::query()->where('store_id', $store->id)->count())->toBe(0);
});

test('journal filter options are available without model binding conflict', function () {
    [$user, $store] = journalUserWithStore();
    journalAccount($store->id, '1-1100', 'Kas');

    $this->actingAs($user)
        ->getJson(route('akuntansi.jurnal-umum.filter-options'))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status_options.0', 'semua')
        ->assertJsonPath('data.accounts.0.account_code', '1-1100');
});

test('posted journal can be reversed only once', function () {
    [$user, $store] = journalUserWithStore();
    $cash = journalAccount($store->id, '1-1100', 'Kas');
    $capital = journalAccount($store->id, '3-1100', 'Modal Pemilik');
    $journal = JournalEntry::query()->create([
        'store_id' => $store->id,
        'journal_no' => 'JU-202608-0001',
        'journal_date' => '2026-08-19',
        'description' => 'Jurnal posted',
        'status' => 'posted',
        'total_debit' => 1000000,
        'total_credit' => 1000000,
        'posted_at' => now(),
        'posted_by' => $user->id,
        'created_by' => $user->id,
    ]);
    $journal->lines()->createMany([
        ['store_id' => $store->id, 'account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0, 'line_order' => 0],
        ['store_id' => $store->id, 'account_id' => $capital->id, 'debit' => 0, 'credit' => 1000000, 'line_order' => 1],
    ]);

    $response = $this->actingAs($user)->post(route('akuntansi.jurnal-umum.reverse', $journal));
    $response->assertRedirect(route('akuntansi.jurnal-umum.index'));

    expect($journal->fresh()->status)->toBe('reversed')
        ->and($journal->fresh()->reversed_entry_id)->not->toBeNull();

    $this->actingAs($user)->post(route('akuntansi.jurnal-umum.reverse', $journal->fresh()))
        ->assertStatus(409);

    $reversal = JournalEntry::query()->where('source_type', 'journal_reversal')->firstOrFail();
    expect($reversal->status)->toBe('posted')
        ->and($reversal->total_debit)->toBe('1000000.00')
        ->and($reversal->total_credit)->toBe('1000000.00');
});

test('journal search and reference number are scoped to the active store', function () {
    [$user, $store] = journalUserWithStore();
    $otherStore = Store::factory()->create();

    JournalEntry::query()->create([
        'store_id' => $store->id,
        'journal_no' => 'JU-202608-0001',
        'journal_date' => '2026-08-19',
        'description' => 'Kas toko aktif',
        'status' => 'draft',
    ]);
    JournalEntry::query()->create([
        'store_id' => $otherStore->id,
        'journal_no' => 'JU-202608-0001',
        'journal_date' => '2026-08-19',
        'description' => 'Kas toko lain',
        'status' => 'draft',
    ]);

    $this->actingAs($user)->getJson('/api/akuntansi/journal-entries/search?search=Kas')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.journal_no', 'JU-202608-0001');

    $this->actingAs($user)->getJson('/api/akuntansi/reference-number?type=journal&date=2026-08-19')
        ->assertSuccessful()
        ->assertJsonPath('data.reference_no', 'JU-202608-0002');
});

test('draft journal cannot be posted after an account is deactivated', function () {
    [$user, $store] = journalUserWithStore();
    $cash = journalAccount($store->id, '1-1100', 'Kas');
    $capital = journalAccount($store->id, '3-1100', 'Modal Pemilik');

    $this->actingAs($user)->post(route('akuntansi.jurnal-umum.store'), [
        'journal_date' => '2026-08-19',
        'description' => 'Jurnal draft',
        'lines' => [
            ['account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000000],
        ],
    ])->assertRedirect();

    $journal = JournalEntry::query()->where('store_id', $store->id)->firstOrFail();
    $cash->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('akuntansi.jurnal-umum.post', $journal))
        ->assertStatus(422);

    expect($journal->fresh()->status)->toBe('draft');
});

test('journal JSON endpoints support detail update and posting', function () {
    [$user, $store] = journalUserWithStore();
    $cash = journalAccount($store->id, '1-1100', 'Kas');
    $capital = journalAccount($store->id, '3-1100', 'Modal Pemilik');

    $createResponse = $this->actingAs($user)->postJson(route('akuntansi.jurnal-umum.store'), [
        'journal_date' => '2026-08-19',
        'description' => 'Jurnal JSON',
        'lines' => [
            ['account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000000],
        ],
    ]);

    $createResponse->assertCreated()->assertJsonPath('success', true);
    $journal = JournalEntry::query()->where('store_id', $store->id)->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('akuntansi.jurnal-umum.show', $journal))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $journal->id);

    $this->actingAs($user)
        ->patchJson(route('akuntansi.jurnal-umum.update', $journal), [
            'journal_date' => '2026-08-19',
            'description' => 'Jurnal JSON diperbarui',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
                ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000000],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Jurnal JSON diperbarui');

    $this->actingAs($user)
        ->postJson(route('akuntansi.jurnal-umum.post', $journal))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'posted');
});
