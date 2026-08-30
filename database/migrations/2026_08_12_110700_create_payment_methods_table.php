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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->enum('type', ['qris', 'debit_card', 'credit_card', 'bank_transfer', 'e_wallet']);
            $table->string('provider', 100)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('qr_image')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index('store_id');
            $table->index(['store_id', 'is_active']);
            $table->index(['store_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
