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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('restrict');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->foreignId('unit_id')->constrained('product_units')->onDelete('restrict');
            $table->foreignId('parent_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->enum('type', ['single', 'variant', 'bundle'])->default('single');
            $table->decimal('sell_price', 15, 2);
            $table->decimal('buy_price', 15, 2);
            $table->integer('initial_stock')->default(0);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('status');
            $table->index('sku');
            $table->index('barcode');
            $table->index('parent_id');
            $table->index('deleted_at');

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fulltext('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
