<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_sales_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->foreignId('sale_id')
                ->constrained('pos_transactions')
                ->cascadeOnDelete();

            $table->foreignId('sale_item_id')
                ->constrained('pos_transaction_items')
                ->cascadeOnDelete();

            $table->foreignId('consignment_item_id')
                ->constrained('consignment_items')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->decimal('quantity_sold', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('supplier_amount', 15, 2);
            $table->decimal('store_margin', 15, 2);
            $table->enum('settlement_status', ['unsettled', 'settled', 'cancelled'])
                ->default('unsettled');
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['store_id', 'sale_item_id', 'consignment_item_id'],
                'uq_csa_sale_item'
            );
            $table->index(
                ['store_id', 'supplier_id', 'settlement_status'],
                'idx_csa_supplier'
            );
            $table->index(['store_id', 'sale_id'], 'idx_csa_sale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_sales_allocations');
    }
};