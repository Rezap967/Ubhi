<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('return_number');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->enum('refund_method', ['tunai', 'saldo_toko', 'tukar_barang']);
            $table->decimal('total_refund', 15, 2)->default(0);
            $table->enum('status', ['pending', 'disetujui', 'ditolak', 'dibatalkan'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'return_number']);
            $table->index('store_id');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('return_number');
            $table->index('refund_method');
            $table->index('status');
            $table->index('approved_by');
            $table->index('approved_at');
            $table->index('created_at');
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->decimal('qty_sold', 15, 3);
            $table->decimal('qty_returned', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->enum('condition', ['baik', 'rusak', 'cacat', 'kadaluarsa']);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('sales_return_id');
            $table->index('order_item_id');
            $table->index('product_id');
            $table->index('condition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};