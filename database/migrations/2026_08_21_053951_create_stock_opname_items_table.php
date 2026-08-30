<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('stock_opname_sessions')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Gudang — penting untuk mencocokkan stock_balances
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            // Snapshot stok sistem saat sesi dibuat
            $table->decimal('system_stock', 15, 2)->default(0);

            // Input stok fisik dari user (nullable sampai diisi)
            $table->decimal('physical_stock', 15, 2)->nullable();

            // physical_stock - system_stock
            $table->decimal('difference', 15, 2)->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Unique per sesi + produk + gudang
            $table->unique(['session_id', 'product_id', 'warehouse_id']);

            // Indexes
            $table->index('session_id');
            $table->index('product_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
