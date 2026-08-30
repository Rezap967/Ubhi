<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->constrained('consignment_items')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnUpdate()->nullOnDelete();
            $table->string('movement_type', 50);
            $table->decimal('quantity_change', 15, 2);
            $table->decimal('quantity_before', 15, 2);
            $table->decimal('quantity_after', 15, 2);
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'consignment_item_id', 'created_at'], 'idx_stock_ledger_store_item_created');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_ledger_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_stock_ledger');
    }
};