<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // Format: OPN-YYYYMMDD-XXX (unique per store)
            $table->string('session_code', 30);

            $table->string('session_name', 255);

            // all_products | per_warehouse | per_category
            $table->string('scope_type', 30);

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            // FK ke product_categories
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();

            // berjalan | disetujui
            $table->string('status', 20)->default('berjalan');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Unique session_code per store
            $table->unique(['store_id', 'session_code']);

            // Indexes
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_sessions');
    }
};
