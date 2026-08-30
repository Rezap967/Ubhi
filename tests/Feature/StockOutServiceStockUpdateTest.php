<?php

use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockOut;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockOutService;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test Owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $this->store = Store::create([
        'owner_id' => $this->user->id,
        'code' => 'STR-001',
        'name' => 'Test Store',
        'email' => 'store@example.com',
        'phone' => '08123456789',
        'address' => 'Test Address',
        'city' => 'Test City',
        'province' => 'Test Province',
        'postal_code' => '12345',
        'currency' => 'IDR',
        'timezone' => 'Asia/Jakarta',
        'business_type' => 'retail',
        'description' => 'Test Store',
        'status' => 'active',
    ]);

    $this->category = Category::create([
        'name' => 'Test Category',
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);

    $this->unit = ProductUnit::create([
        'name' => 'Pcs',
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);

    $this->warehouse = Warehouse::create([
        'store_id' => $this->store->id,
        'name' => 'Gudang Utama',
        'code' => 'WH-001',
        'warehouse_type' => 'warehouse',
        'created_by' => $this->user->id,
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'name' => 'Test Product',
        'type' => 'single',
        'sell_price' => 10000,
        'buy_price' => 8000,
        'initial_stock' => 10,
        'created_by' => $this->user->id,
    ]);

    $this->service = app(StockOutService::class);
});

test('it decreases_product_stock_and_stock_balance_when_stock_out_is_stored', function () {
    $this->service->store($this->store->id, $this->user->id, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'unit_id' => $this->unit->id,
        'quantity' => 4,
        'reason' => 'rusak',
        'out_date' => now()->toDateString(),
        'reference_no' => 'REF-001',
        'notes' => 'Pengeluaran stock test',
    ]);

    $this->product->refresh();

    $stockBalance = StockBalance::where('store_id', $this->store->id)
        ->where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($this->product->initial_stock)->toBe(6);
    expect($stockBalance)->not->toBeNull();
    expect((float) $stockBalance->quantity)->toBe(6.0);
});

test('it_creates_stock_out_record_for_the_product', function () {
    $this->service->store($this->store->id, $this->user->id, [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'unit_id' => $this->unit->id,
        'quantity' => 2,
        'reason' => 'expired',
        'out_date' => now()->toDateString(),
        'reference_no' => 'REF-002',
        'notes' => 'Expired stock test',
    ]);

    expect(StockOut::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->exists())->toBeTrue();
});
