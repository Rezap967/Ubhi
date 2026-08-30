<?php

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosReceipt;
use App\Models\Kasir\PosSession;
use App\Models\Kasir\PosTransaction;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ownerRole = Role::firstOrCreate(['name' => 'owner']);
    $this->kasirRole = Role::firstOrCreate(['name' => 'kasir']);

    $owner = User::factory()->create(['role_id' => $this->ownerRole->id]);
    $this->store = Store::factory()->create(['owner_id' => $owner->id]);

    $this->kasir = User::factory()->create(['role_id' => $this->kasirRole->id]);
    // Simulate user having access to this store
    $this->kasir->profile()->create(['store_id' => $this->store->id, 'full_name' => 'Kasir 1']);

    $otherOwner = User::factory()->create(['role_id' => $this->ownerRole->id]);
    $this->otherStore = Store::factory()->create(['owner_id' => $otherOwner->id]);
    $this->otherKasir = User::factory()->create(['role_id' => $this->kasirRole->id]);
    $this->otherKasir->profile()->create(['store_id' => $this->otherStore->id, 'full_name' => 'Kasir 2']);

    $session = PosSession::create([
        'store_id' => $this->store->id,
        'user_id' => $this->kasir->id,
        'device_id' => 'POS-01',
        'status' => 'open',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);

    $cart = PosCart::create([
        'store_id' => $this->store->id,
        'session_id' => $session->id,
        'user_id' => $this->kasir->id,
        'customer_id' => null,
        'status' => 'active',
        'subtotal' => 10000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 10000,
    ]);

    $this->transaction = PosTransaction::create([
        'transaction_number' => 'TRX-'.uniqid(),
        'idempotency_key' => uniqid(),
        'store_id' => $this->store->id,
        'session_id' => $session->id,
        'cart_id' => $cart->id,
        'user_id' => $this->kasir->id,
        'customer_id' => null,
        'status' => 'completed',
        'subtotal' => 10000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 10000,
        'paid_amount' => 10000,
        'change_amount' => 0,
        'source' => 'online',
        'transaction_at' => now(),
    ]);
});

it('can fetch receipt for an authorized transaction', function () {
    $response = $this->actingAs($this->kasir)
        ->getJson("/api/v1/pos/transactions/{$this->transaction->id}/receipt");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'id',
                'transaction_id',
                'receipt_number',
                'receipt_payload',
                'print_count',
                'printed_at',
            ],
        ]);

    $this->assertDatabaseHas('pos_receipts', [
        'transaction_id' => $this->transaction->id,
        'print_count' => 0,
        'printed_at' => null,
    ]);
});

it('generates receipt payload only once', function () {
    // 1st request generates payload
    $this->actingAs($this->kasir)
        ->getJson("/api/v1/pos/transactions/{$this->transaction->id}/receipt");

    $receipt = PosReceipt::where('transaction_id', $this->transaction->id)->first();
    expect($receipt)->not->toBeNull();

    // update something to prove it's not regenerated
    $receipt->update(['print_count' => 5]);

    // 2nd request
    $response = $this->actingAs($this->kasir)
        ->getJson("/api/v1/pos/transactions/{$this->transaction->id}/receipt");

    $response->assertJsonPath('data.print_count', 5);
});

it('can print receipt and increments print count', function () {
    $response = $this->actingAs($this->kasir)
        ->postJson("/api/v1/pos/transactions/{$this->transaction->id}/print");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.print_count', 1);

    expect($response->json('data.printed_at'))->not->toBeNull();

    $this->assertDatabaseHas('pos_receipts', [
        'transaction_id' => $this->transaction->id,
        'print_count' => 1,
    ]);
});

it('denies access to transaction from another store', function () {
    $response = $this->actingAs($this->otherKasir)
        ->getJson("/api/v1/pos/transactions/{$this->transaction->id}/receipt");

    $response->assertStatus(403);

    $responsePrint = $this->actingAs($this->otherKasir)
        ->postJson("/api/v1/pos/transactions/{$this->transaction->id}/print");

    $responsePrint->assertStatus(403);
});

it('does not change transaction data on print', function () {
    $transactionBefore = PosTransaction::find($this->transaction->id);

    $this->actingAs($this->kasir)
        ->postJson("/api/v1/pos/transactions/{$this->transaction->id}/print");

    $transactionAfter = PosTransaction::find($this->transaction->id);

    expect($transactionBefore->toArray())->toEqual($transactionAfter->toArray());
});
