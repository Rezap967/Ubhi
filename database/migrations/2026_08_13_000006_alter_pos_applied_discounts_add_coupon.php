<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_applied_discounts', function (Blueprint $table) {
            // Add coupon reference (nullable because applied discount can be promotion or coupon)
            $table->foreignId('coupon_id')->nullable()->after('promotion_id')->constrained('coupons')->nullOnDelete();

            // Index for coupon lookups
            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_applied_discounts', function (Blueprint $table) {
            $table->dropForeignKey(['coupon_id']);
            $table->dropColumn('coupon_id');
        });
    }
};
