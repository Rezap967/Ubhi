<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_transaction_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('pos_transactions')
                ->cascadeOnDelete();

            // nullable: jika produk dihapus, snapshot tetap tersimpan
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Snapshot data produk saat checkout
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('unit_name', 50)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('quantity', 10, 3);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->index('transaction_id');
            $table->index('product_id');
            $table->index('sku');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transaction_items');
    }
};
