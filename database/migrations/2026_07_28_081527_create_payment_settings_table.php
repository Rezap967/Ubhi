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
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('provider', 100)->nullable();
            $table->string('merchant_id', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->enum('payment_type', ['qris', 'transfer', 'ewallet', 'cash']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('store_id');
            $table->index('status');
            $table->index('payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
