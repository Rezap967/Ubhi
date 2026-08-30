<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // Format: TRF-YYYYMMDD-XXX (unique per store)
            $table->string('transfer_code', 30);

            $table->foreignId('from_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('to_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->date('transfer_date');

            // selesai
            $table->string('status', 20)->default('selesai');

            $table->text('notes')->nullable();

            $table->unsignedInteger('total_items')->default(0);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            // Constraints & Indexes
            $table->unique(['store_id', 'transfer_code']);
            $table->index(['store_id', 'transfer_date']);
            $table->index(['store_id', 'from_warehouse_id']);
            $table->index(['store_id', 'to_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_sessions');
    }
};
