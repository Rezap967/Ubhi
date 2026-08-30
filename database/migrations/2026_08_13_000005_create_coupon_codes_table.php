<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('used_at')->nullable(); // Set on checkout success
            $table->foreignId('pos_transaction_id')->nullable()->constrained('pos_transactions')->nullOnDelete();
            $table->timestamps();

            // Indexes for redemption log queries
            $table->index('coupon_id');
            $table->index(['coupon_id', 'used_by']); // For per-customer limit validation
            $table->index(['coupon_id', 'pos_transaction_id']); // For POS redemptions
            $table->index('used_at');

            // NOTE: No unique constraints on nullable fields.
            // Duplicate redemption checked explicitly in CheckoutService.
            // NOTE: order_id removed; POS system uses pos_transaction_id only.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_codes');
    }
};
