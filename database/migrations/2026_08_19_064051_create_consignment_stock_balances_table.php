<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->constrained('consignment_items')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('quantity_available', 15, 2)->default(0.00);
            $table->decimal('quantity_sold', 15, 2)->default(0.00);
            $table->decimal('quantity_returned', 15, 2)->default(0.00);
            $table->string('last_movement_type', 50)->nullable();
            $table->string('last_reference_no', 100)->nullable();
            $table->foreignId('last_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'consignment_item_id', 'warehouse_id'], 'uq_consignment_stock_balance');
            $table->index(['store_id', 'supplier_id'], 'idx_stock_balances_store_supp');
            $table->index(['store_id', 'product_id', 'warehouse_id'], 'idx_stock_balances_store_prod_wh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_stock_balances');
    }
};