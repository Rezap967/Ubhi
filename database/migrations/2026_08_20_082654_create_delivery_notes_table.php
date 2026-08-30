<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('delivery_number');
            $table->enum('source_type', ['transaksi_penjualan', 'order_pelanggan']);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('recipient_name');
            $table->text('recipient_address')->nullable();
            $table->date('delivery_date')->nullable();
            $table->enum('status', ['draft', 'dikirim', 'terkirim', 'dibatalkan'])->default('draft');
            $table->timestamp('printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['store_id', 'delivery_number']);
            $table->index('store_id');
            $table->index('delivery_number');
            $table->index('source_type');
            $table->index('order_id');
            $table->index('customer_order_id');
            $table->index('status');
            $table->index('delivery_date');
            $table->index('printed_at');
            $table->index('created_at');
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->index('delivery_note_id');
            $table->index('order_item_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};