<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('pos_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('unit_name', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(1.000);
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('source', 30)->nullable();
            $table->timestamps();

            $table->index('cart_id');
            $table->index('product_id');
            $table->index('barcode');
            $table->unique(['cart_id', 'product_id'], 'uq_cart_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cart_items');
    }
};
