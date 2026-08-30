<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('transaction_number', 40)->unique();
            $table->string('idempotency_key', 120)->unique();

            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('pos_sessions');
            $table->foreignId('cart_id')->nullable()->constrained('pos_carts')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->enum('status', ['completed', 'void_requested', 'voided', 'failed'])->default('completed');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);

            $table->enum('source', ['online', 'offline_sync'])->default('online');
            $table->text('notes')->nullable();
            $table->timestamp('transaction_at');

            $table->timestamps();

            $table->index('store_id');
            $table->index('session_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('transaction_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
    }
};
