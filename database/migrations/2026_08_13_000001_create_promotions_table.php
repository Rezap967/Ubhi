<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['diskon_persen', 'potongan_tetap']);
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_purchase', 15, 2)->nullable();
            $table->unsignedInteger('min_qty')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('scope', ['semua_produk', 'per_produk', 'per_kategori', 'per_brand', 'per_grup_pelanggan']);
            $table->unsignedInteger('max_usage')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->enum('status', ['draft', 'aktif', 'nonaktif', 'berakhir', 'diarsipkan'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('store_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
