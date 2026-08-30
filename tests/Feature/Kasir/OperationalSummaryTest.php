<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TEST_DEVICE_ID = 'POS-SUMMARY-TEST-001';

// ─── Helpers ─────────────────────────────────────────────────────────────

function osSeedRoles(): void
{
    foreach (['owner', 'kasir', 'manajer', 'petugas_gudang'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function osMakeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role']), 'store' => $store];
}

function osMakeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role']);
}

function osMakeManager(Store $store): User
{
    $role = Role::where('name', 'manajer')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role']);
}

function osOpenSession(User $user, Store $store, int $openingCash = 500000): PosSession
{
    return PosSession::create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'device_id' => TEST_DEVICE_ID,
        'status' => 'open',
        'opening_cash' => $openingCash,
        'opened_at' => now()->subHour(),
    ]);
}

function osMakeTransaction(PosSession $session, string $status = 'completed', float $amount = 50000): PosTransaction
{
    $cart = PosCart::create([
        'store_id' => $session->store_id,
        'session_id' => $session->id,
        'user_id' => $session->user_id,
        'status' => 'checked_out',
        'subtotal' => $amount,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $amount,
    ]);

    return PosTransaction::create([
        'transaction_number' => 'TRX-'.uniqid(),
        'idempotency_key' => uniqid(),
        'store_id' => $session->store_id,
        'session_id' => $session->id,
        'cart_id' => $cart->id,
        'user_id' => $session->user_id,
        'status' => $status,
        'subtotal' => $amount,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $amount,
        'paid_amount' => $amount,
        'change_amount' => 0,
        'source' => 'online',
        'transaction_at' => now(),
    ]);
}

function osMakePayment(PosTransaction $transaction, string $method = 'cash', ?float $amount = null): PosPayment
{
    return PosPayment::create([
        'transaction_id' => $transaction->id,
        'payment_method' => $method,
        'amount' => $amount ?? (float) $transaction->grand_total,
        'change_amount' => 0,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

// ─── Tests ─────────────────────────────────────────────────────────────

beforeEach(function () {
    osSeedRoles();
});

it('kasir bisa melihat footer summary sesi aktif', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    $session = osOpenSession($kasir, $store);

    $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk()
        ->assertJsonPath('data.session.session_id', $session->id)
        ->assertJsonPath('data.session.opening_cash', 500000)
        ->assertJsonPath('data.transactions.transaction_count', 0);
});

it('owner bisa melihat footer summary sesi aktif', function () {
    ['user' => $owner, 'store' => $store] = osMakeOwnerWithStore();
    $session = osOpenSession($owner, $store);

    $this->actingAs($owner)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk()
        ->assertJsonPath('data.session.session_id', $session->id);
});

it('user tanpa sesi aktif mendapat 409', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    // Tidak buka sesi

    $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Sesi kasir belum dibuka.');
});

it('role selain kasir atau owner ditolak', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $manager = osMakeManager($store);

    $this->actingAs($manager)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertForbidden(); // Access Denied from Role Middleware
});

it('summary menghitung transaksi pada session aktif dengan benar', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    $session = osOpenSession($kasir, $store, 100000); // open cash 100k

    // Trx 1: Cash 50k
    $trx1 = osMakeTransaction($session, 'completed', 50000);
    osMakePayment($trx1, 'cash', 50000);

    // Trx 2: QRIS 75k
    $trx2 = osMakeTransaction($session, 'completed', 75000);
    osMakePayment($trx2, 'qris', 75000);

    $response = $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk();

    $data = $response->json('data');

    // Expected values
    expect($data['transactions']['transaction_count'])->toBe(2)
        ->and($data['transactions']['gross_revenue'])->toBe(125000)
        ->and($data['transactions']['net_revenue'])->toBe(125000)
        ->and($data['payments']['cash_total'])->toBe(50000)
        ->and($data['payments']['qris_total'])->toBe(75000)
        ->and($data['payments']['non_cash_total'])->toBe(75000)
        ->and($data['session']['opening_cash'])->toBe(100000)
        ->and($data['session']['expected_cash'])->toBe(150000); // 100k + 50k
});

it('summary tidak menghitung transaksi session lain', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir1 = osMakeKasir($store);
    $kasir2 = osMakeKasir($store);

    $session1 = osOpenSession($kasir1, $store, 0);
    $session2 = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir2->id,
        'device_id' => 'OTHER-DEVICE',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    // Trx milik session 2
    $trx2 = osMakeTransaction($session2, 'completed', 100000);
    osMakePayment($trx2, 'cash', 100000);

    // Kasir 1 cek summary
    $response = $this->actingAs($kasir1)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk();

    $data = $response->json('data');
    expect($data['transactions']['transaction_count'])->toBe(0)
        ->and($data['transactions']['net_revenue'])->toBe(0);
});

it('summary exclude voided transactions', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    $session = osOpenSession($kasir, $store, 0);

    // Trx Completed
    $trx1 = osMakeTransaction($session, 'completed', 50000);
    osMakePayment($trx1, 'cash', 50000);

    // Trx Voided
    $trx2 = osMakeTransaction($session, 'voided', 20000);
    osMakePayment($trx2, 'cash', 20000);

    $response = $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk();

    $data = $response->json('data');

    // Only includes completed
    expect($data['transactions']['transaction_count'])->toBe(1)
        ->and($data['transactions']['net_revenue'])->toBe(50000)
        ->and($data['payments']['cash_total'])->toBe(50000)
        ->and($data['transactions']['voided_count'])->toBe(1)
        ->and($data['transactions']['voided_amount'])->toBe(20000);
});

it('durasi sesi dan meta tersedia', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    // Opened 1 hour ago (3600 seconds)
    $session = osOpenSession($kasir, $store);

    $response = $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/footer?device_id='.TEST_DEVICE_ID)
        ->assertOk();

    $data = $response->json('data');
    expect($data['session']['duration_seconds'])->toBeGreaterThanOrEqual(3600)
        ->and($data['meta']['server_time'])->not->toBeEmpty();
});

it('summary/session mengembalikan data yang sama dengan footer untuk MVP', function () {
    ['store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);
    $session = osOpenSession($kasir, $store);

    $response = $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/session?device_id='.TEST_DEVICE_ID)
        ->assertOk();

    expect($response->json('data.session.session_id'))->toBe($session->id);
});

it('all-sessions endpoint hanya dapat diakses owner', function () {
    ['user' => $owner, 'store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);

    $this->actingAs($kasir)
        ->getJson('/api/v1/pos/summary/all-sessions')
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson('/api/v1/pos/summary/all-sessions')
        ->assertOk();
});

it('all-sessions aggregate semua data hari ini untuk store', function () {
    ['user' => $owner, 'store' => $store] = osMakeOwnerWithStore();
    $kasir = osMakeKasir($store);

    $session1 = osOpenSession($owner, $store, 100000);
    $trx1 = osMakeTransaction($session1, 'completed', 20000);
    osMakePayment($trx1, 'cash', 20000);

    $session2 = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => 'DEV-2',
        'status' => 'closed',
        'opening_cash' => 200000,
        'expected_cash' => 250000,
        'closing_cash' => 250000,
        'cash_difference' => 0,
        'opened_at' => now()->subHours(2),
        'closed_at' => now()->subHour(),
    ]);
    $trx2 = osMakeTransaction($session2, 'completed', 50000);
    osMakePayment($trx2, 'cash', 50000);

    $response = $this->actingAs($owner)
        ->getJson('/api/v1/pos/summary/all-sessions')
        ->assertOk();

    $data = $response->json('data');
    expect($data['sessions_count'])->toBe(2)
        ->and($data['transaction_count'])->toBe(2)
        ->and($data['net_revenue'])->toBe(70000)
        ->and($data['cash_total'])->toBe(70000)
        ->and($data['total_opening_cash'])->toBe(300000);
});
