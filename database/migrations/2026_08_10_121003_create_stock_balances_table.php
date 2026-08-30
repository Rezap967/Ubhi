<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();

            // Store / tenant pemilik stok
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // Produk yang memiliki stok
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Gudang tempat stok disimpan.
            // NULL = stok umum / tidak terikat gudang.
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            // Stok terkini
            $table->decimal('quantity', 15, 2)
                ->default(0);

            // Metadata perubahan stok terakhir
            $table->string('last_movement_type', 50)
                ->nullable();

            $table->string('last_reference_no', 100)
                ->nullable();

            $table->decimal('last_quantity_change', 15, 2)
                ->nullable();

            $table->foreignId('last_changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('last_changed_at')
                ->nullable();

            $table->timestamps();

            // Index untuk query berdasarkan toko
            $table->index('store_id');

            // Index kombinasi product + warehouse
            $table->index(['product_id', 'warehouse_id']);

            // Index untuk pencarian stok berdasarkan toko dan produk
            $table->index(['store_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
