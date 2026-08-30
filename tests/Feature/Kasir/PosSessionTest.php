<?php

use App\Models\Kasir\PosAuditLog;
use App\Models\Kasir\PosSession;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function seedRoles(): void
{
    foreach (['owner', 'kasir', 'petugas_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function makeOwnerWithStore(): array
{
    $role = Role::where('name', 'owner')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return ['user' => $user->fresh(['role', 'profile']), 'store' => $store];
}

function makeKasir(Store $store): User
{
    $role = Role::where('name', 'kasir')->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function makeUserWithRole(string $roleName, Store $store): User
{
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

const DEVICE_ID = 'POS-TEST-001';

// ──────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────

beforeEach(function () {
    seedRoles();
});

// ──────────────────────────────────────────────
// Test 1: Kasir bisa membuka sesi POS
// ──────────────────────────────────────────────

test('kasir bisa membuka sesi POS', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    $response = $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 500000,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => ['status' => 'open'],
        ]);

    expect(PosSession::where('user_id', $kasir->id)->where('status', 'open')->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Test 2: Owner bisa membuka sesi POS
// ──────────────────────────────────────────────

test('owner bisa membuka sesi POS', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 1000000,
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});

// ──────────────────────────────────────────────
// Test 3: Staff/gudang/manajer tidak bisa membuka sesi POS
// ──────────────────────────────────────────────

test('petugas gudang tidak bisa membuka sesi POS', function () {
    ['store' => $store] = makeOwnerWithStore();
    $gudang = makeUserWithRole('petugas_gudang', $store);

    Sanctum::actingAs($gudang);

    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 100000,
    ])->assertStatus(403);
});

test('manajer tidak bisa membuka sesi POS', function () {
    ['store' => $store] = makeOwnerWithStore();
    $manajer = makeUserWithRole('manajer', $store);

    Sanctum::actingAs($manajer);

    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 100000,
    ])->assertStatus(403);
});

// ──────────────────────────────────────────────
// Test 4: opening_cash wajib numeric dan min 0
// ──────────────────────────────────────────────

test('opening_cash wajib numeric dan tidak boleh negatif', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    // Non-numeric
    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 'abc',
    ])->assertStatus(422);

    // Negative
    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => -100,
    ])->assertStatus(422);
});

// ──────────────────────────────────────────────
// Test 5: device_id wajib
// ──────────────────────────────────────────────

test('device_id wajib saat membuka sesi', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    $this->postJson('/api/v1/pos/sessions/open', [
        'opening_cash' => 500000,
    ])->assertStatus(422)->assertJsonValidationErrors(['device_id']);
});

// ──────────────────────────────────────────────
// Test 6: user tidak bisa membuka dua session open pada device yang sama
// ──────────────────────────────────────────────

test('tidak bisa membuka dua sesi open pada device yang sama', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    // First session
    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 500000,
    ])->assertStatus(201);

    // Second attempt — same device
    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 200000,
    ])->assertStatus(409)->assertJson([
        'success' => false,
        'message' => 'Sesi kasir masih aktif pada device ini.',
    ]);
});

// ──────────────────────────────────────────────
// Test 7: user bisa mengambil current session
// ──────────────────────────────────────────────

test('bisa mengambil sesi aktif berdasarkan device', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/pos/sessions/current?device_id='.DEVICE_ID)
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => ['session' => ['status' => 'open']],
        ]);
});

// ──────────────────────────────────────────────
// Test 8: current session return null jika belum ada sesi
// ──────────────────────────────────────────────

test('current session null jika tidak ada sesi aktif', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/pos/sessions/current?device_id=NO-SESSION-DEVICE')
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => ['session' => null],
        ]);
});

// ──────────────────────────────────────────────
// Test 9: kasir bisa menutup sesi miliknya sendiri
// ──────────────────────────────────────────────

test('kasir bisa menutup sesi miliknya sendiri', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($kasir);

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 750000,
    ])->assertStatus(200)->assertJson([
        'success' => true,
        'data' => ['status' => 'closed'],
    ]);

    expect($session->fresh()->status)->toBe('closed');
});

// ──────────────────────────────────────────────
// Test 10: kasir tidak bisa menutup sesi user lain
// ──────────────────────────────────────────────

test('kasir tidak bisa menutup sesi kasir lain', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasirA = makeKasir($store);
    $kasirB = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasirA->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($kasirB); // Kasir B coba tutup sesi Kasir A

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 600000,
    ])->assertStatus(403);
});

// ──────────────────────────────────────────────
// Test 11: owner bisa menutup sesi kasir di store yang sama
// ──────────────────────────────────────────────

test('owner bisa menutup sesi kasir di store yang sama', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 800000,
    ])->assertStatus(200)->assertJson(['success' => true]);
});

// ──────────────────────────────────────────────
// Test 12: tidak bisa menutup sesi yang sudah closed
// ──────────────────────────────────────────────

test('tidak bisa menutup sesi yang sudah ditutup', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'closed',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'closing_cash' => 700000,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    Sanctum::actingAs($kasir);

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 700000,
    ])->assertStatus(409)->assertJson([
        'message' => 'Sesi kasir sudah ditutup.',
    ]);
});

// ──────────────────────────────────────────────
// Test 13: close session menghitung cash_difference
// ──────────────────────────────────────────────

test('close session menghitung cash_difference dengan benar', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($kasir);

    $closingCash = 750000;
    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => $closingCash,
    ])->assertStatus(200);

    $fresh = $session->fresh();
    // expected_cash = opening_cash (tahap awal, tidak ada transaksi)
    expect((float) $fresh->cash_difference)->toBe((float) ($closingCash - $fresh->expected_cash));
});

// ──────────────────────────────────────────────
// Test 14: open session mencatat audit log
// ──────────────────────────────────────────────

test('open session mencatat pos_audit_logs', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    Sanctum::actingAs($kasir);

    $this->postJson('/api/v1/pos/sessions/open', [
        'device_id' => DEVICE_ID,
        'opening_cash' => 500000,
    ])->assertStatus(201);

    expect(PosAuditLog::where('action', 'open_session')->where('user_id', $kasir->id)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Test 15: close session mencatat audit log
// ──────────────────────────────────────────────

test('close session mencatat pos_audit_logs', function () {
    ['user' => $owner, 'store' => $store] = makeOwnerWithStore();
    $kasir = makeKasir($store);

    $session = PosSession::create([
        'store_id' => $store->id,
        'user_id' => $kasir->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    Sanctum::actingAs($kasir);

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 600000,
    ])->assertStatus(200);

    expect(PosAuditLog::where('action', 'close_session')->where('auditable_id', $session->id)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Test 16: store scope aman — user tidak bisa akses session store lain
// ──────────────────────────────────────────────

test('store scope aman — kasir tidak bisa menutup sesi dari store lain', function () {
    // Store A with its kasir
    ['user' => $ownerA, 'store' => $storeA] = makeOwnerWithStore();
    $kasirA = makeKasir($storeA);

    // Store B with its kasir
    ['user' => $ownerB, 'store' => $storeB] = makeOwnerWithStore();
    $kasirB = makeKasir($storeB);

    // Session belongs to Store A
    $session = PosSession::create([
        'store_id' => $storeA->id,
        'user_id' => $kasirA->id,
        'device_id' => DEVICE_ID,
        'status' => 'open',
        'opening_cash' => 500000,
        'expected_cash' => 500000,
        'opened_at' => now(),
    ]);

    // Kasir from Store B tries to close Store A's session
    Sanctum::actingAs($kasirB);

    $this->patchJson("/api/v1/pos/sessions/{$session->id}/close", [
        'closing_cash' => 500000,
    ])->assertStatus(403);

    expect($session->fresh()->status)->toBe('open');
});
