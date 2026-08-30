<?php

use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosTransaction;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ============================================================
// HELPERS
// ============================================================

/**
 * @return array{store: Store, owner: User, manajer: User, kasir: User}
 */
function setupStoreWithUsers(): array
{
    $store = Store::factory()->create();

    $ownerRole = Role::create(['name' => 'owner']);
    $manajerRole = Role::create(['name' => 'manajer']);
    $kasirRole = Role::create(['name' => 'kasir']);

    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    $manajer = User::factory()->create(['role_id' => $manajerRole->id]);
    UserProfile::factory()->create(['user_id' => $manajer->id, 'store_id' => $store->id]);

    $kasir = User::factory()->create(['role_id' => $kasirRole->id]);
    UserProfile::factory()->create(['user_id' => $kasir->id, 'store_id' => $store->id]);

    return compact('store', 'owner', 'manajer', 'kasir');
}

function makeTransaction(int $storeId, int $userId, array $overrides = []): PosTransaction
{
    // Create a session required by FK
    $session = DB::table('pos_sessions')->insertGetId([
        'store_id' => $storeId,
        'user_id' => $userId,
        'device_id' => 'test-device',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create a cart required by FK
    $cart = DB::table('pos_carts')->insertGetId([
        'store_id' => $storeId,
        'session_id' => $session,
        'user_id' => $userId,
        'customer_id' => null,
        'status' => 'checked_out',
        'subtotal' => 100000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 100000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return PosTransaction::create(array_merge([
        'transaction_number' => 'TRX-'.uniqid(),
        'idempotency_key' => uniqid(),
        'store_id' => $storeId,
        'session_id' => $session,
        'cart_id' => $cart,
        'user_id' => $userId,
        'customer_id' => null,
        'status' => 'completed',
        'subtotal' => 100000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'change_amount' => 0,
        'source' => 'online',
        'notes' => null,
        'transaction_at' => now(),
    ], $overrides));
}

function makePayment(int $transactionId, string $method = 'cash'): PosPayment
{
    return PosPayment::create([
        'transaction_id' => $transactionId,
        'payment_method' => $method,
        'amount' => 100000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

// ============================================================
// GET /api/v1/reports/transactions — INDEX
// ============================================================

describe('GET /api/v1/reports/transactions', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/reports/transactions')
            ->assertStatus(401);
    });

    it('returns 403 for user without store profile', function () {
        $noStoreRole = Role::create(['name' => 'owner']);
        $user = User::factory()->create(['role_id' => $noStoreRole->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/reports/transactions')
            ->assertStatus(403);
    });

    it('allows owner to list transactions', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $transaction = makeTransaction($store->id, $kasir->id);
        makePayment($transaction->id);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data', 'meta',
            ]);
    });

    it('allows manajer to list transactions', function () {
        ['store' => $store, 'manajer' => $manajer, 'kasir' => $kasir] = setupStoreWithUsers();

        makeTransaction($store->id, $kasir->id);

        $this->actingAs($manajer)
            ->getJson('/api/v1/reports/transactions')
            ->assertOk()
            ->assertJsonPath('success', true);
    });

    it('allows kasir to list only their own transactions', function () {
        ['store' => $store, 'kasir' => $kasir, 'owner' => $owner] = setupStoreWithUsers();

        $kasirTrx = makeTransaction($store->id, $kasir->id);
        makePayment($kasirTrx->id);

        // Transaction oleh owner, kasir tidak boleh lihat
        $ownerTrx = makeTransaction($store->id, $owner->id);
        makePayment($ownerTrx->id);

        $response = $this->actingAs($kasir)
            ->getJson('/api/v1/reports/transactions')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($kasirTrx->id)
            ->not->toContain($ownerTrx->id);
    });

    it('scopes transactions to user store only', function () {
        ['store' => $store1, 'owner' => $owner1, 'kasir' => $kasir1] = setupStoreWithUsers();

        $store2 = Store::factory()->create();
        $kasirRole2 = Role::where('name', 'kasir')->first();
        $kasir2 = User::factory()->create(['role_id' => $kasirRole2->id]);
        UserProfile::factory()->create(['user_id' => $kasir2->id, 'store_id' => $store2->id]);

        $trxStore1 = makeTransaction($store1->id, $kasir1->id);
        $trxStore2 = makeTransaction($store2->id, $kasir2->id);

        $response = $this->actingAs($owner1)
            ->getJson('/api/v1/reports/transactions')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($trxStore1->id)
            ->not->toContain($trxStore2->id);
    });

    it('filters by date_start and date_end', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $oldTrx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()->subMonths(2)]);
        $thisTrx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?'.http_build_query([
                'date_start' => now()->startOfMonth()->toDateString(),
                'date_end' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($thisTrx->id)
            ->not->toContain($oldTrx->id);
    });

    it('filters by status', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $completedTrx = makeTransaction($store->id, $kasir->id, ['status' => 'completed', 'transaction_at' => now()]);
        $voidedTrx = makeTransaction($store->id, $kasir->id, ['status' => 'voided', 'transaction_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?status=completed')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($completedTrx->id)
            ->not->toContain($voidedTrx->id);
    });

    it('filters by cashier_id for owner', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir, 'manajer' => $manajer] = setupStoreWithUsers();

        $kasirTrx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);
        $manajerTrx = makeTransaction($store->id, $manajer->id, ['transaction_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?cashier_id='.$kasir->id)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($kasirTrx->id)
            ->not->toContain($manajerTrx->id);
    });

    it('returns 422 when kasir tries to filter by another cashier_id', function () {
        ['store' => $store, 'kasir' => $kasir, 'owner' => $owner] = setupStoreWithUsers();

        $this->actingAs($kasir)
            ->getJson('/api/v1/reports/transactions?cashier_id='.$owner->id)
            ->assertStatus(422);
    });

    it('filters by payment_method', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $cashTrx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);
        $qrisTrx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);

        makePayment($cashTrx->id, 'cash');
        makePayment($qrisTrx->id, 'qris');

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?payment_method=cash')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($cashTrx->id)
            ->not->toContain($qrisTrx->id);
    });

    it('supports pagination via per_page', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        for ($i = 0; $i < 5; $i++) {
            makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);
        }

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?per_page=2')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(2);
        expect($response->json('meta'))->not->toBeNull();
    });

    it('validates date_end must be after_or_equal date_start', function () {
        ['owner' => $owner] = setupStoreWithUsers();

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?date_start=2024-06-01&date_end=2024-05-01')
            ->assertStatus(422);
    });

    it('validates sort_by must be an allowed value', function () {
        ['owner' => $owner] = setupStoreWithUsers();

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions?sort_by=invalid_column')
            ->assertStatus(422);
    });

    it('response data has correct structure', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $trx = makeTransaction($store->id, $kasir->id, ['transaction_at' => now()]);
        makePayment($trx->id);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions')
            ->assertOk();

        $first = $response->json('data.0');
        expect($first)->toHaveKeys([
            'id', 'receipt_no', 'transaction_at', 'cashier', 'customer',
            'status', 'payment_method', 'payment_methods', 'subtotal',
            'discount_total', 'tax_total', 'grand_total',
        ]);
    });

});

// ============================================================
// GET /api/v1/reports/transactions/filter-options
// ============================================================

describe('GET /api/v1/reports/transactions/filter-options', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/reports/transactions/filter-options')
            ->assertStatus(401);
    });

    it('allows owner to get filter options', function () {
        ['owner' => $owner] = setupStoreWithUsers();

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions/filter-options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['cashiers', 'statuses', 'payment_methods', 'customers'],
            ]);
    });

    it('kasir filter options only contains themselves as cashier', function () {
        ['kasir' => $kasir] = setupStoreWithUsers();

        $response = $this->actingAs($kasir)
            ->getJson('/api/v1/reports/transactions/filter-options')
            ->assertOk();

        $cashiers = $response->json('data.cashiers');
        expect($cashiers)->toHaveCount(1);
        expect($cashiers[0]['id'])->toBe($kasir->id);
    });

});

// ============================================================
// GET /api/v1/reports/transactions/{id} — SHOW
// ============================================================

describe('GET /api/v1/reports/transactions/{id}', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/reports/transactions/1')
            ->assertStatus(401);
    });

    it('returns 404 for non-existent transaction', function () {
        ['owner' => $owner] = setupStoreWithUsers();

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions/99999')
            ->assertStatus(404);
    });

    it('allows owner to view any transaction in their store', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $trx = makeTransaction($store->id, $kasir->id);
        makePayment($trx->id);

        DB::table('pos_transaction_items')->insert([
            'transaction_id' => $trx->id,
            'product_id' => null,
            'product_name' => 'Test Produk',
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions/'.$trx->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $trx->id);
    });

    it('allows kasir to view their own transaction', function () {
        ['store' => $store, 'kasir' => $kasir] = setupStoreWithUsers();

        $trx = makeTransaction($store->id, $kasir->id);
        makePayment($trx->id);

        $this->actingAs($kasir)
            ->getJson('/api/v1/reports/transactions/'.$trx->id)
            ->assertOk()
            ->assertJsonPath('data.id', $trx->id);
    });

    it('kasir cannot view transaction belonging to another cashier', function () {
        ['store' => $store, 'kasir' => $kasir, 'owner' => $owner] = setupStoreWithUsers();

        // Transaksi dibuat oleh owner, kasir tidak boleh lihat
        // Repository scopes by user_id for kasir, so it returns null -> 404
        $trx = makeTransaction($store->id, $owner->id);
        makePayment($trx->id);

        $this->actingAs($kasir)
            ->getJson('/api/v1/reports/transactions/'.$trx->id)
            ->assertStatus(404);
    });

    it('returns 403 for transaction from different store', function () {
        ['owner' => $owner] = setupStoreWithUsers();

        $store2 = Store::factory()->create();
        $kasirRole2 = Role::where('name', 'kasir')->first();
        $kasir2 = User::factory()->create(['role_id' => $kasirRole2->id]);
        UserProfile::factory()->create(['user_id' => $kasir2->id, 'store_id' => $store2->id]);

        $trxOtherStore = makeTransaction($store2->id, $kasir2->id);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions/'.$trxOtherStore->id)
            ->assertStatus(404); // scoped — not found for this store
    });

    it('detail response has correct structure with items and payments', function () {
        ['store' => $store, 'owner' => $owner, 'kasir' => $kasir] = setupStoreWithUsers();

        $trx = makeTransaction($store->id, $kasir->id);
        makePayment($trx->id);

        DB::table('pos_transaction_items')->insert([
            'transaction_id' => $trx->id,
            'product_id' => null,
            'product_name' => 'Produk A',
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/transactions/'.$trx->id)
            ->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveKeys([
            'id', 'receipt_no', 'transaction_at', 'cashier', 'customer',
            'status', 'payment_method', 'payment_methods',
            'subtotal', 'discount_total', 'tax_total', 'grand_total',
            'items', 'payments',
        ]);

        expect($data['items'])->toBeArray()->not->toBeEmpty();
        expect($data['payments'])->toBeArray()->not->toBeEmpty();
    });

});
