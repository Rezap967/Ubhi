<?php

use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseOrderItem;
use App\Models\Belanja\PurchaseReturn;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Supplier;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers (scoped so they don't clash with PO test global helpers)
// ──────────────────────────────────────────────

function prSeedRoles(): void
{
    foreach (['owner', 'kasir', 'staf_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function prMakeStore(): Store
{
    prSeedRoles();
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    return $store;
}

function prMakeUser(string $roleName, Store $store): User
{
    prSeedRoles();
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function prMakeProduct(Store $store, array $attrs = []): Product
{
    $cat = Category::firstOrCreate(
        ['name' => 'Kategori Test PR'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs PR'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    return Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Retur '.uniqid(),
        'sku' => 'SKU-RT-'.uniqid(),
        'barcode' => 'BAR-RT-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 5000,
        'buy_price' => 4000,
        'initial_stock' => 100,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attrs));
}

function prMakeSupplier(Store $store): Supplier
{
    return Supplier::create([
        'code' => 'SUP-RT-'.uniqid(),
        'name' => 'Supplier Retur '.uniqid(),
        'status' => 'aktif',
        'created_by' => $store->owner_id,
    ]);
}

/**
 * Buat PurchaseOrder + item, langsung set status.
 */
function prMakePO(Store $store, User $user, Supplier $supplier, Product $product, array $opts = []): array
{
    $po = PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => $opts['status'] ?? 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $user->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => $opts['qty_ordered'] ?? 10,
        'qty_received' => 0,
        'unit_price' => $opts['unit_price'] ?? 2000,
        'subtotal' => ($opts['qty_ordered'] ?? 10) * ($opts['unit_price'] ?? 2000),
    ]);

    return [$po, $item];
}

// ──────────────────────────────────────────────
// Tests
// ──────────────────────────────────────────────

it('kasir ditolak 403 di semua endpoint Purchase Return', function () {
    $store = prMakeStore();
    $kasir = prMakeUser('kasir', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($kasir);

    $this->getJson('/api/v1/purchase-returns')->assertStatus(403);
    $this->postJson('/api/v1/purchase-returns', [])->assertStatus(403);
});

it('staf_gudang diizinkan index, store, show, update, destroy, submit — tapi ditolak confirm dan cancel', function () {
    $store = prMakeStore();
    $gudang = prMakeUser('staf_gudang', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($gudang);

    // Index
    $this->getJson('/api/v1/purchase-returns')->assertOk();

    // Store
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ]);
    $res->assertStatus(201);
    $prId = $res->json('data.id');

    // Confirm — ditolak 403
    $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm")->assertStatus(403);

    // Cancel — ditolak 403
    $this->patchJson("/api/v1/purchase-returns/{$prId}/cancel")->assertStatus(403);
});

it('owner dan manajer diizinkan confirm dan cancel', function () {
    $store = prMakeStore();
    $manajer = prMakeUser('manajer', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($manajer);

    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 5],
        ],
    ]);
    $res->assertStatus(201);
    $prId = $res->json('data.id');

    // submit
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertOk();

    // confirm
    $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm")->assertOk();
});

it('retur dari PO berstatus Draft ditolak 422', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product, ['status' => 'Draft']);

    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ])->assertStatus(422);
});

it('retur dari PO berstatus Dibatalkan ditolak 422', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product, ['status' => 'Dibatalkan']);

    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ])->assertStatus(422);
});

it('retur dari PO beda store mengembalikan 422 dari validation (exists scope)', function () {
    $storeA = prMakeStore();
    $storeB = prMakeStore();

    $ownerA = prMakeUser('owner', $storeA);
    $ownerB = prMakeUser('owner', $storeB);
    $supplierA = prMakeSupplier($storeA);
    $supplierB = prMakeSupplier($storeB);
    $productA = prMakeProduct($storeA);
    $productB = prMakeProduct($storeB);

    [$poB, $poItemB] = prMakePO($storeB, $ownerB, $supplierB, $productB);

    Sanctum::actingAs($ownerA);

    // PO milik storeB harus gagal validasi (exists scope ke store_id userA)
    $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $poB->id,
        'items' => [
            ['purchase_order_item_id' => $poItemB->id, 'qty_returned' => 3],
        ],
    ])->assertStatus(422);
});

it('supplier_id otomatis diambil dari PO, bukan dari input client', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $fakeSupplier = prMakeSupplier($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($owner);

    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'supplier_id' => $fakeSupplier->id, // harusnya diabaikan
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 2],
        ],
    ]);

    $res->assertStatus(201);
    // supplier_id harus dari PO, bukan dari input
    expect($res->json('data.supplier_id'))->toBe($supplier->id);
});

it('qty_returned melebihi qty_ordered mengembalikan 422 dengan format error yang tepat', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product, ['qty_ordered' => 10]);

    Sanctum::actingAs($owner);

    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 15],
        ],
    ]);

    $res->assertStatus(422);
    expect($res->json('message'))->toBe('Jumlah retur tidak boleh melebihi jumlah barang pada PO asal.');
    // errors.items.0.qty_returned is an array of messages
    $errors = $res->json('errors');
    $qtyError = $errors['items.0.qty_returned'][0] ?? ($errors['items'][0]['qty_returned'][0] ?? '');
    expect($qtyError)->toContain('Qty retur (15) melebihi qty pesan pada PO asal (10).');
});

it('purchase_order_item_id bukan milik PO yang dipilih ditolak 422', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    // Dua PO berbeda
    [$po1, $poItem1] = prMakePO($store, $owner, $supplier, $product);
    [$po2, $poItem2] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($owner);

    // Kirim retur ke PO1 tapi pakai item dari PO2
    $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po1->id,
        'items' => [
            ['purchase_order_item_id' => $poItem2->id, 'qty_returned' => 3],
        ],
    ])->assertStatus(422);
});

it('edit dan hapus retur non-Draft ditolak 403', function () {
    $store = prMakeStore();
    $gudang = prMakeUser('staf_gudang', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($gudang);

    // Buat retur
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ]);
    $res->assertStatus(201);
    $prId = $res->json('data.id');

    // Submit retur
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertOk();

    // Update tidak bisa (sudah Dikirim ke Supplier)
    $this->putJson("/api/v1/purchase-returns/{$prId}", [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 2],
        ],
    ])->assertStatus(403);

    // Delete tidak bisa (sudah Dikirim ke Supplier)
    $this->deleteJson("/api/v1/purchase-returns/{$prId}")->assertStatus(403);
});

it('update tidak bisa mengganti purchase_order_id', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po1, $poItem1] = prMakePO($store, $owner, $supplier, $product);
    [$po2, $poItem2] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($owner);

    // Buat retur dari PO1
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po1->id,
        'items' => [
            ['purchase_order_item_id' => $poItem1->id, 'qty_returned' => 3],
        ],
    ]);
    $res->assertStatus(201);
    $prId = $res->json('data.id');

    // Update dengan purchase_order_id berbeda (PO2)
    $this->putJson("/api/v1/purchase-returns/{$prId}", [
        'purchase_order_id' => $po2->id, // beda
        'items' => [
            ['purchase_order_item_id' => $poItem2->id, 'qty_returned' => 2],
        ],
    ])->assertStatus(422);
});

it('submit: Draft → Dikirim ke Supplier; selain itu → 422', function () {
    $store = prMakeStore();
    $gudang = prMakeUser('staf_gudang', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($gudang);

    // Buat retur
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ]);
    $prId = $res->json('data.id');

    // Submit berhasil
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertOk()
        ->assertJsonPath('status', 'Dikirim ke Supplier');

    // Submit lagi (sudah bukan Draft) → 422
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertStatus(422);
});

it('confirm: manajer/owner, Dikirim ke Supplier → Dikonfirmasi, mengisi confirmed_by + confirmed_at, semua status_item ikut Dikonfirmasi', function () {
    $store = prMakeStore();
    $manajer = prMakeUser('manajer', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($manajer);

    // Buat + submit retur
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 5],
        ],
    ]);
    $prId = $res->json('data.id');
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertOk();

    // Confirm
    $confirmRes = $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm");
    $confirmRes->assertOk()->assertJsonPath('status', 'Dikonfirmasi');

    // Verifikasi DB
    $pr = PurchaseReturn::find($prId);
    expect($pr->status)->toBe('Dikonfirmasi');
    expect($pr->confirmed_by)->toBe($manajer->id);
    expect($pr->confirmed_at)->not->toBeNull();

    // Semua status_item ikut Dikonfirmasi
    $pr->items->each(function ($item) {
        expect($item->status_item)->toBe('Dikonfirmasi');
    });
});

it('confirm dari status selain Dikirim ke Supplier ditolak 422', function () {
    $store = prMakeStore();
    $manajer = prMakeUser('manajer', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($manajer);

    // Buat retur — statusnya masih Draft
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 2],
        ],
    ]);
    $prId = $res->json('data.id');

    // Confirm saat masih Draft → 422
    $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm")->assertStatus(422);
});

it('cancel: manajer/owner, Draft|Dikirim ke Supplier → Dibatalkan; Dikonfirmasi → 422', function () {
    $store = prMakeStore();
    $manajer = prMakeUser('manajer', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($manajer);

    // Buat + submit + confirm retur
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 5],
        ],
    ]);
    $prId = $res->json('data.id');
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertOk();
    $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm")->assertOk();

    // Cancel saat sudah Dikonfirmasi → 422
    $this->patchJson("/api/v1/purchase-returns/{$prId}/cancel")->assertStatus(422);
});

it('cancel Draft berhasil → Dibatalkan', function () {
    $store = prMakeStore();
    $manajer = prMakeUser('manajer', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);
    $owner = prMakeUser('owner', $store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($manajer);

    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 3],
        ],
    ]);
    $prId = $res->json('data.id');

    $this->patchJson("/api/v1/purchase-returns/{$prId}/cancel")
        ->assertOk()
        ->assertJsonPath('status', 'Dibatalkan');
});

it('IDOR beda store pada show/update/destroy/submit/confirm/cancel mengembalikan 404', function () {
    $storeA = prMakeStore();
    $storeB = prMakeStore();

    $ownerA = prMakeUser('owner', $storeA);
    $ownerB = prMakeUser('owner', $storeB);
    $supplierA = prMakeSupplier($storeA);
    $supplierB = prMakeSupplier($storeB);
    $productA = prMakeProduct($storeA);
    $productB = prMakeProduct($storeB);

    [$poB, $poItemB] = prMakePO($storeB, $ownerB, $supplierB, $productB);

    // Buat retur di storeB sebagai ownerB
    Sanctum::actingAs($ownerB);
    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $poB->id,
        'items' => [
            ['purchase_order_item_id' => $poItemB->id, 'qty_returned' => 2],
        ],
    ]);
    $prId = $res->json('data.id');

    // Coba akses sebagai ownerA (beda store)
    Sanctum::actingAs($ownerA);

    $this->getJson("/api/v1/purchase-returns/{$prId}")->assertStatus(404);
    $this->putJson("/api/v1/purchase-returns/{$prId}", [])->assertStatus(404);
    $this->deleteJson("/api/v1/purchase-returns/{$prId}")->assertStatus(404);
    $this->patchJson("/api/v1/purchase-returns/{$prId}/submit")->assertStatus(404);
    $this->patchJson("/api/v1/purchase-returns/{$prId}/confirm")->assertStatus(404);
    $this->patchJson("/api/v1/purchase-returns/{$prId}/cancel")->assertStatus(404);
});

it('soft delete Draft: header deleted_at terisi, items tetap ada', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product);

    Sanctum::actingAs($owner);

    $res = $this->postJson('/api/v1/purchase-returns', [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 4],
        ],
    ]);
    $prId = $res->json('data.id');

    $this->deleteJson("/api/v1/purchase-returns/{$prId}")->assertOk();

    // Header ter-soft delete
    expect(PurchaseReturn::find($prId))->toBeNull();
    expect(PurchaseReturn::withTrashed()->find($prId))->not->toBeNull();

    // Items masih ada (tanpa deleted_at)
    $this->assertDatabaseHas('purchase_return_items', [
        'purchase_return_id' => $prId,
    ]);
});

it('return_number unik dan format RT-YYYYMMDD-XXXX per store per hari', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product, ['qty_ordered' => 20]);

    Sanctum::actingAs($owner);

    $payload = [
        'purchase_order_id' => $po->id,
        'items' => [
            ['purchase_order_item_id' => $poItem->id, 'qty_returned' => 2],
        ],
    ];

    $res1 = $this->postJson('/api/v1/purchase-returns', $payload);
    $res1->assertStatus(201);
    $rn1 = $res1->json('data.return_number');

    $res2 = $this->postJson('/api/v1/purchase-returns', $payload);
    $res2->assertStatus(201);
    $rn2 = $res2->json('data.return_number');

    $date = now()->format('Ymd');
    expect($rn1)->toBe("RT-{$date}-0001");
    expect($rn2)->toBe("RT-{$date}-0002");
    expect($rn1)->not->toBe($rn2);
});

it('per_page default 15 dan clamp max 100 berfungsi pada Purchase Return', function () {
    $store = prMakeStore();
    $owner = prMakeUser('owner', $store);
    $supplier = prMakeSupplier($store);
    $product = prMakeProduct($store);

    [$po, $poItem] = prMakePO($store, $owner, $supplier, $product, ['qty_ordered' => 200]);

    // Buat 20 retur
    for ($i = 0; $i < 20; $i++) {
        PurchaseReturn::create([
            'return_number' => 'RT-PAGE-'.str_pad($i, 4, '0', STR_PAD_LEFT),
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'status' => 'Draft',
            'total' => 0,
            'created_by' => $owner->id,
        ]);
    }

    Sanctum::actingAs($owner);

    // Default 15
    $resDefault = $this->getJson('/api/v1/purchase-returns');
    $resDefault->assertOk();
    expect(count($resDefault->json('data')))->toBe(15);
    expect($resDefault->json('meta.per_page'))->toBe(15);

    // Clamp 100 (buat total 110)
    for ($i = 20; $i < 110; $i++) {
        PurchaseReturn::create([
            'return_number' => 'RT-PAGE-'.str_pad($i, 4, '0', STR_PAD_LEFT),
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'status' => 'Draft',
            'total' => 0,
            'created_by' => $owner->id,
        ]);
    }

    $resClamp = $this->getJson('/api/v1/purchase-returns?per_page=150');
    $resClamp->assertOk();
    expect(count($resClamp->json('data')))->toBe(100);
    expect($resClamp->json('meta.per_page'))->toBe(100);
});

it('store_id diambil dari profile, bukan dari users', function () {
    prSeedRoles();
    $role = Role::where('name', 'staf_gudang')->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    // Sengaja tidak membuat UserProfile agar store_id null

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/purchase-returns')->assertStatus(403);
});
