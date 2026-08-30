<?php

namespace Tests\Feature\Stock;

use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Warehouse;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\ReceiptService;
use Tests\TestCase;

class ReceiptServiceStockUpdateTest extends TestCase
{
    public function test_it_increases_product_stock_when_receipt_is_stored(): void
    {
        $user = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $store = Store::create([
            'owner_id' => $user->id,
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

        $category = Category::create([
            'name' => 'Test Category',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $unit = ProductUnit::create([
            'name' => 'Pcs',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $warehouse = Warehouse::create([
            'store_id' => $store->id,
            'name' => 'Gudang Utama',
            'code' => 'WH-001',
            'warehouse_type' => 'warehouse',
            'created_by' => $user->id,
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Product',
            'type' => 'single',
            'sell_price' => 10000,
            'buy_price' => 8000,
            'initial_stock' => 10,
            'created_by' => $user->id,
        ]);

        $service = app(ReceiptService::class);

        $service->store($store->id, $user->id, [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'unit_id' => $unit->id,
            'quantity' => 5,
            'purchase_price' => 9000,
            'payment_status' => 'lunas',
            'received_date' => now()->toDateString(),
            'notes' => 'test receipt',
        ]);

        $product->refresh();

        $this->assertSame(15, (int) $product->initial_stock);
    }

    public function test_it_decreases_product_stock_again_when_receipt_is_deleted(): void
    {
        $user = User::create([
            'name' => 'Test Owner 2',
            'email' => 'owner2@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $store = Store::create([
            'owner_id' => $user->id,
            'code' => 'STR-002',
            'name' => 'Test Store 2',
            'email' => 'store2@example.com',
            'phone' => '08123456790',
            'address' => 'Test Address 2',
            'city' => 'Test City 2',
            'province' => 'Test Province 2',
            'postal_code' => '12346',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'business_type' => 'retail',
            'description' => 'Test Store 2',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Test Category 2',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $unit = ProductUnit::create([
            'name' => 'Pcs 2',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $warehouse = Warehouse::create([
            'store_id' => $store->id,
            'name' => 'Gudang Utama 2',
            'code' => 'WH-002',
            'warehouse_type' => 'warehouse',
            'created_by' => $user->id,
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Product 2',
            'type' => 'single',
            'sell_price' => 10000,
            'buy_price' => 8000,
            'initial_stock' => 10,
            'created_by' => $user->id,
        ]);

        $service = app(ReceiptService::class);

        $receipt = $service->store($store->id, $user->id, [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'unit_id' => $unit->id,
            'quantity' => 5,
            'purchase_price' => 9000,
            'payment_status' => 'lunas',
            'received_date' => now()->toDateString(),
            'notes' => 'test receipt',
        ]);

        $service->delete($store->id, $receipt->id);

        $product->refresh();

        $this->assertSame(10, (int) $product->initial_stock);
    }
}
