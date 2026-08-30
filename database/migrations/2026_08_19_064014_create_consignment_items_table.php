<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('selling_price', 15, 2)->default(0.00);
            $table->decimal('consignment_price', 15, 2)->default(0.00);
            $table->enum('commission_type', ['margin', 'percentage', 'fixed'])->default('margin');
            $table->decimal('commission_value', 15, 2)->default(0.00);
            $table->enum('status', ['active', 'inactive', 'closed'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'product_id', 'supplier_id'], 'uq_consignment_item_store_prod_supp');
            $table->index(['store_id', 'supplier_id', 'status'], 'idx_consignment_items_store_supp_status');
            $table->index(['store_id', 'product_id'], 'idx_consignment_items_store_prod');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
    }
};