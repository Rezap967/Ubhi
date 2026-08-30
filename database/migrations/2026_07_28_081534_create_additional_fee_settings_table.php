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
        Schema::create('additional_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade')->onUpdate('cascade');
            $table->string('name', 100);
            $table->enum('fee_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            // Unique Constraint
            $table->unique(['store_id', 'name'], 'unique_store_fee_name');

            // Indexes for Search & Filter Performance
            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'fee_type']);
            $table->index(['store_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_fee_settings');
    }
};