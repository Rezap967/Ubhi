<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // stock_out (penjualan), stock_in (pembelian/penerimaan), adjustment, dll.
            $table->string('movement_type', 50);

            $table->decimal('quantity', 10, 3);
            $table->decimal('before_quantity', 15, 3)->nullable();
            $table->decimal('after_quantity', 15, 3)->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('movement_at');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('store_id');
            $table->index('product_id');
            $table->index('movement_type');
            $table->index('movement_at');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
