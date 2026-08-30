<?php

use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseOrderItem;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Supplier;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Stock\StockIn;
use App\Models\Stock\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function poSeedRoles(): void
{
    foreach (['owner', 'kasir', 'staf_gudang', 'manajer'] as $name) {
        Role::firstOrCreate(['name' => $name]);
    }
}

function poMakeStore(): Store
{
    poSeedRoles();
    $ownerRole = Role::where('name', 'owner')->first();
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $store = Store::factory()->create(['owner_id' => $owner->id]);
    UserProfile::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);

    return $store;
}

function poMakeUser(string $roleName, Store $store): User
{
    poSeedRoles();
    $role = Role::where('name', $roleName)->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    return $user->fresh(['role', 'profile']);
}

function poMakeProduct(Store $store, array $attrs = []): Product
{
    $cat = Category::firstOrCreate(
        ['name' => 'Kategori Test'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );
    $unit = ProductUnit::firstOrCreate(
        ['name' => 'Pcs'],
        ['status' => 'active', 'created_by' => $store->owner_id, 'updated_by' => $store->owner_id]
    );

    return Product::create(array_merge([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Test '.uniqid(),
        'sku' => 'SKU-'.uniqid(),
        'barcode' => 'BAR-'.uniqid(),
        'type' => 'single',
        'status' => 'aktif',
        'sell_price' => 5000,
        'buy_price' => 4000,
        'initial_stock' => 100,
        'created_by' => $store->owner_id,
        'updated_by' => $store->owner_id,
    ], $attrs));
}

function poMakeSupplier(Store $store): Supplier
{
    return Supplier::create([
        'code' => 'SUP-'.uniqid(),
        'name' => 'Supplier Test '.uniqid(),
        'status' => 'aktif',
        'created_by' => $store->owner_id,
    ]);
}

// ──────────────────────────────────────────────
// Tests
// ──────────────────────────────────────────────

it('kasir tidak diizinkan mengakses semua endpoint PO', function () {
    $store = poMakeStore();
    $kasir = poMakeUser('kasir', $store);
    Sanctum::actingAs($kasir);

    // Index
    $this->getJson('/api/v1/purchase-orders')->assertStatus(403);

    // Store
    $this->postJson('/api/v1/purchase-orders', [])->assertStatus(403);
});

it('staf_gudang diizinkan mengakses index, show, store, update, destroy, submit, receive, tapi dilarang cancel', function () {
    $store = poMakeStore();
    $gudang = poMakeUser('staf_gudang', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($gudang);

    // Create Draft
    $payload = [
        'supplier_id' => $supplier->id,
        'jenis_pembayaran' => 'Kredit',
        'estimasi_tiba' => now()->addDays(2)->format('Y-m-d'),
        'notes' => 'Catatan test',
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 10,
                'unit_price' => 2000,
                'expiry_date' => now()->addYear()->format('Y-m-d'),
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/purchase-orders', $payload);
    $response->assertStatus(201);
    $poId = $response->json('data.id');

    // Show
    $this->getJson("/api/v1/purchase-orders/{$poId}")->assertOk();

    // Update
    $payload['notes'] = 'Updated notes';
    $this->putJson("/api/v1/purchase-orders/{$poId}", $payload)->assertOk();

    // Submit
    $this->patchJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();

    // Cancel (Harus 403 untuk staf_gudang)
    $this->patchJson("/api/v1/purchase-orders/{$poId}/cancel")->assertStatus(403);

    // Gudang can receive
    $receivePayload = [
        'items' => [
            [
                'purchase_order_item_id' => PurchaseOrder::find($poId)->items->first()->id,
                'qty_received' => 5,
            ],
        ],
    ];
    $this->patchJson("/api/v1/purchase-orders/{$poId}/receive", $receivePayload)->assertOk();

    // Delete Draft PO
    $poDraft = PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Draft',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $gudang->id,
    ]);
    $this->deleteJson("/api/v1/purchase-orders/{$poDraft->id}")->assertOk();
});

it('owner atau manajer diizinkan melakukan cancel PO', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Draft',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/purchase-orders/{$po->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', 'Dibatalkan');
});

it('tidak membiarkan IDOR lintas store dan mengembalikan 404', function () {
    $storeA = poMakeStore();
    $storeB = poMakeStore();

    $userA = poMakeUser('staf_gudang', $storeA);
    $userB = poMakeUser('staf_gudang', $storeB);

    $supplierA = poMakeSupplier($storeA);

    $poA = PurchaseOrder::create([
        'po_number' => 'PO-STORE-A',
        'supplier_id' => $supplierA->id,
        'store_id' => $storeA->id,
        'status' => 'Draft',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $userA->id,
    ]);

    // User B mencoba mengakses PO milik Store A
    Sanctum::actingAs($userB);

    $this->getJson("/api/v1/purchase-orders/{$poA->id}")->assertStatus(404);
    $this->putJson("/api/v1/purchase-orders/{$poA->id}", [])->assertStatus(404);
    $this->deleteJson("/api/v1/purchase-orders/{$poA->id}")->assertStatus(404);
    $this->patchJson("/api/v1/purchase-orders/{$poA->id}/submit")->assertStatus(404);
});

it('hanya mengizinkan edit dan hapus pada status Draft', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-SUBMITTED',
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    // Edit non-Draft -> 403
    $payload = [
        'supplier_id' => $supplier->id,
        'jenis_pembayaran' => 'Kredit',
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 5,
                'unit_price' => 1000,
            ],
        ],
    ];
    $this->putJson("/api/v1/purchase-orders/{$po->id}", $payload)->assertStatus(403);

    // Hapus non-Draft -> 403
    $this->deleteJson("/api/v1/purchase-orders/{$po->id}")->assertStatus(403);
});

it('hanya mengizinkan submit pada status Draft', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-RECEIVED',
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Diterima',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/purchase-orders/{$po->id}/submit")->assertStatus(422);
});

it('penerimaan barang bersifat incremental dan tidak boleh melebihi qty dipesan', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($owner);

    $po = $po = PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 2000,
        'subtotal' => 20000,
    ]);

    // Receive pertama: 6 (sukses)
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [
            ['purchase_order_item_id' => $item->id, 'qty_received' => 6],
        ],
    ])->assertOk();

    expect($item->fresh()->qty_received)->toBe(6);
    expect($po->fresh()->status)->toBe('Sebagian');

    // Receive kedua: 5 (6 + 5 = 11 > 10, harus gagal)
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [
            ['purchase_order_item_id' => $item->id, 'qty_received' => 5],
        ],
    ])->assertStatus(422);

    expect($item->fresh()->qty_received)->toBe(6); // tetap 6
});

it('mengubah status menjadi Diterima jika semua qty_pending lunas', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 5,
        'unit_price' => 2000,
        'subtotal' => 20000,
    ]);

    // Receive sisa lunas: 5 (lunas)
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [
            ['purchase_order_item_id' => $item->id, 'qty_received' => 5],
        ],
    ])->assertOk();

    expect($item->fresh()->qty_received)->toBe(10);
    expect($po->fresh()->status)->toBe('Diterima');
});

it('berhasil soft delete Draft PO tanpa error database constraint', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-SOFT-DELETE',
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Draft',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 2000,
        'subtotal' => 20000,
    ]);

    $this->deleteJson("/api/v1/purchase-orders/{$po->id}")->assertOk();

    // Header harus ter-soft delete
    expect(PurchaseOrder::find($po->id))->toBeNull();
    expect(PurchaseOrder::withTrashed()->find($po->id))->not->toBeNull();

    // Item tetap ada di DB (karena tidak soft-deleted)
    $this->assertDatabaseHas('purchase_order_items', [
        'id' => $item->id,
        'purchase_order_id' => $po->id,
    ]);
});

it('menolak akses jika profile user tidak memiliki store_id', function () {
    $store = poMakeStore();
    $role = Role::where('name', 'staf_gudang')->first();
    $user = User::factory()->create(['role_id' => $role->id]);
    // Jangan buat UserProfile agar profile/store_id bernilai null

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/purchase-orders')->assertStatus(403);
});

it('filter-options mengembalikan data ter-scope store_id dan aktif saja', function () {
    $storeA = poMakeStore();
    $storeB = poMakeStore();

    $userA = poMakeUser('staf_gudang', $storeA);

    // Active product for Store A
    $productActiveA = poMakeProduct($storeA, ['status' => 'aktif']);
    // Inactive product for Store A
    $productInactiveA = poMakeProduct($storeA, ['status' => 'nonaktif']);
    // Active product for Store B (should not be visible to A)
    $productActiveB = poMakeProduct($storeB, ['status' => 'aktif']);

    // Active Supplier
    $supplierActive = poMakeSupplier($storeA);
    $supplierActive->update(['status' => 'aktif']);
    // Inactive Supplier
    $supplierInactive = poMakeSupplier($storeA);
    $supplierInactive->update(['status' => 'nonaktif']);

    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/v1/purchase-orders/filter-options');
    $response->assertOk();

    $products = collect($response->json('data.products'));
    expect($products->pluck('id'))->toContain($productActiveA->id);
    expect($products->pluck('id'))->not->toContain($productInactiveA->id);
    expect($products->pluck('id'))->not->toContain($productActiveB->id);

    $suppliers = collect($response->json('data.suppliers'));
    expect($suppliers->pluck('id'))->toContain($supplierActive->id);
    expect($suppliers->pluck('id'))->not->toContain($supplierInactive->id);
});

it('per_page pagination default 15 dan clamp max 100 berfungsi', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);

    // Bikin 20 PO
    for ($i = 0; $i < 20; $i++) {
        PurchaseOrder::create([
            'po_number' => 'PO-PAGE-'.$i,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'status' => 'Draft',
            'jenis_pembayaran' => 'Kredit',
            'created_by' => $owner->id,
        ]);
    }

    Sanctum::actingAs($owner);

    // Default pagination (15)
    $resDefault = $this->getJson('/api/v1/purchase-orders');
    $resDefault->assertOk();
    expect(count($resDefault->json('data')))->toBe(15);
    expect($resDefault->json('meta.per_page'))->toBe(15);

    // Clamp pagination limit to max 100
    // (Bikin total 110 PO)
    for ($i = 20; $i < 110; $i++) {
        PurchaseOrder::create([
            'po_number' => 'PO-PAGE-'.$i,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'status' => 'Draft',
            'jenis_pembayaran' => 'Kredit',
            'created_by' => $owner->id,
        ]);
    }

    $resClamp = $this->getJson('/api/v1/purchase-orders?per_page=150');
    $resClamp->assertOk();
    expect(count($resClamp->json('data')))->toBe(100);
    expect($resClamp->json('meta.per_page'))->toBe(100);
});

it('dapat memfilter status menggunakan alias English', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);

    // PO status Dikirim (Submitted)
    $poSubmitted = PurchaseOrder::create([
        'po_number' => 'PO-SUB',
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    // PO status Sebagian (Partial)
    $poPartial = PurchaseOrder::create([
        'po_number' => 'PO-PART',
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Sebagian',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    // Query status=Submitted -> harus dapat status Dikirim
    $resSub = $this->getJson('/api/v1/purchase-orders?status=Submitted');
    $resSub->assertOk();
    expect(collect($resSub->json('data'))->pluck('id'))->toContain($poSubmitted->id);
    expect(collect($resSub->json('data'))->pluck('id'))->not->toContain($poPartial->id);

    // Query status=Partial -> harus dapat status Sebagian
    $resPart = $this->getJson('/api/v1/purchase-orders?status=Partial');
    $resPart->assertOk();
    expect(collect($resPart->json('data'))->pluck('id'))->toContain($poPartial->id);
    expect(collect($resPart->json('data'))->pluck('id'))->not->toContain($poSubmitted->id);
});

it('po_number auto-increment unik per store per hari', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($owner);

    $payload = [
        'supplier_id' => $supplier->id,
        'jenis_pembayaran' => 'Kredit',
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 5,
                'unit_price' => 1000,
            ],
        ],
    ];

    $res1 = $this->postJson('/api/v1/purchase-orders', $payload);
    $res1->assertStatus(201);
    $poNumber1 = $res1->json('data.po_number'); // e.g. PO-YYYYMMDD-0001

    $res2 = $this->postJson('/api/v1/purchase-orders', $payload);
    $res2->assertStatus(201);
    $poNumber2 = $res2->json('data.po_number'); // e.g. PO-YYYYMMDD-0002

    expect($poNumber1)->not->toBe($poNumber2);

    $date = now()->format('Ymd');
    expect($poNumber1)->toBe("PO-{$date}-0001");
    expect($poNumber2)->toBe("PO-{$date}-0002");
});

// ──────────────────────────────────────────────
// Stock Integration Tests (FT-BELANJA-01)
// ──────────────────────────────────────────────

it('receive items menambah saldo stock_balances sesuai qty_received', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store, ['initial_stock' => 0]);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-STOCK-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 20,
        'qty_received' => 0,
        'unit_price' => 3000,
        'subtotal' => 60000,
    ]);

    $res = $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [
            ['purchase_order_item_id' => $item->id, 'qty_received' => 15],
        ],
    ]);

    $res->assertOk();

    // Stock balance harus bertambah 15
    $this->assertDatabaseHas('stock_balances', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 15,
    ]);

    // initial_stock produk juga harus bertambah 15 (sumber kebenaran stok)
    expect($product->fresh()->initial_stock)->toBe(15);

    // Harus mencatat di tabel stock_ins dengan reference_no nomor PO
    $this->assertDatabaseHas('stock_ins', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'reference_no' => $po->po_number,
        'quantity' => 15,
    ]);
});

it('receive items mencatat stock_movements dengan reference yang benar', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store, ['initial_stock' => 0]);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-MOV-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 2000,
        'subtotal' => 20000,
    ]);

    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [
            ['purchase_order_item_id' => $item->id, 'qty_received' => 10],
        ],
    ])->assertOk();

    // Harus ada record stock_movements dengan referensi PO dan tipe 'in'
    $this->assertDatabaseHas('stock_movements', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'reference_type' => 'purchase_order_receipt',
        'reference_id' => $po->id,
        'movement_type' => 'in',
        'quantity' => 10,
        'before_quantity' => 0,
        'after_quantity' => 10,
    ]);
});

it('receive items dua kali mengakumulasi stok dengan benar', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store, ['initial_stock' => 0]);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-ACCUM-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 20,
        'qty_received' => 0,
        'unit_price' => 1500,
        'subtotal' => 30000,
    ]);

    // Penerimaan pertama: 8
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [['purchase_order_item_id' => $item->id, 'qty_received' => 8]],
    ])->assertOk();

    expect($po->fresh()->status)->toBe('Sebagian');

    // Penerimaan kedua: 12 (total = 20, lunas)
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [['purchase_order_item_id' => $item->id, 'qty_received' => 12]],
    ])->assertOk();

    expect($po->fresh()->status)->toBe('Diterima');

    // Stok akhir harus 20 (8+12)
    $this->assertDatabaseHas('stock_balances', [
        'store_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    // Harus ada 2 baris stock_movements
    $movementCount = StockMovement::where('product_id', $product->id)
        ->where('reference_type', 'purchase_order_receipt')
        ->where('reference_id', $po->id)
        ->count();

    expect($movementCount)->toBe(2);
});

it('receive items menolak jika qty_received melebihi qty_pending dan stok tidak berubah', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store, ['initial_stock' => 0]);

    Sanctum::actingAs($owner);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-OVR-'.uniqid(),
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'status' => 'Dikirim',
        'jenis_pembayaran' => 'Kredit',
        'created_by' => $owner->id,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
        'unit_price' => 1000,
        'subtotal' => 5000,
    ]);

    // Coba receive melebihi qty_ordered
    $this->patchJson("/api/v1/purchase-orders/{$po->id}/receive", [
        'items' => [['purchase_order_item_id' => $item->id, 'qty_received' => 10]],
    ])->assertStatus(422);

    // Stok tidak boleh berubah
    $this->assertDatabaseMissing('stock_balances', [
        'store_id' => $store->id,
        'product_id' => $product->id,
    ]);

    // Tidak ada movement
    $this->assertDatabaseMissing('stock_movements', [
        'product_id' => $product->id,
        'reference_type' => 'purchase_order_receipt',
        'reference_id' => $po->id,
    ]);
});

it('menolak penghapusan penerimaan barang manual jika terikat dengan Purchase Order (PO-)', function () {
    $store = poMakeStore();
    $owner = poMakeUser('owner', $store);
    $supplier = poMakeSupplier($store);
    $product = poMakeProduct($store);

    Sanctum::actingAs($owner);

    // Buat data stock_in dengan reference PO
    $receipt = StockIn::create([
        'stock_in_code' => 'STM-PO-TEST',
        'store_id' => $store->id,
        'product_id' => $product->id,
        'warehouse_id' => null,
        'supplier_id' => $supplier->id,
        'quantity' => 10,
        'purchase_price' => 1000,
        'payment_status' => 'lunas',
        'reference_no' => 'PO-20260820-0001',
        'received_date' => now()->format('Y-m-d'),
        'status' => 'selesai',
        'created_by' => $owner->id,
    ]);

    // Panggil API delete receipt, harus menolak (422) karena berasal dari PO
    $this->deleteJson("/api/v1/stock/receipt/{$receipt->id}")
        ->assertStatus(422);

    // Pastikan data masih ada di database
    $this->assertDatabaseHas('stock_ins', [
        'id' => $receipt->id,
    ]);
});
