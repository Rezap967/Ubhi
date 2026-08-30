<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_applied_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained('pos_carts')->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('promotion_name');
            $table->enum('scope', ['semua_produk', 'per_produk', 'per_kategori', 'per_brand', 'per_grup_pelanggan']);
            $table->enum('discount_type', ['diskon_persen', 'potongan_tetap']);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('source')->default('auto');
            $table->enum('status', ['active', 'expired', 'removed'])->default('active');
            $table->timestamps();

            $table->index(['cart_id', 'status']);
            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_applied_discounts');
    }
};
