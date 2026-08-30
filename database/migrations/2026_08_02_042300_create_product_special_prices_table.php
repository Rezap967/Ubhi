<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_special_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('product_units')->onDelete('restrict');
            $table->decimal('min_qty', 10, 2);
            $table->decimal('max_qty', 10, 2)->nullable();
            $table->decimal('special_price', 15, 2);
            $table->foreignId('customer_group_id')->nullable()->comment('References customer_groups.id');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'unit_id']);
            $table->index(['valid_from', 'valid_until']);
            $table->index('is_active');
            $table->index('customer_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_special_prices');
    }
};
