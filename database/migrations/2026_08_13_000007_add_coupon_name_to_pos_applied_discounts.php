<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_applied_discounts', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_applied_discounts', 'coupon_name')) {
                $table->string('coupon_name')->nullable()->after('promotion_name');
            }

            if (! Schema::hasColumn('pos_applied_discounts', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('promotion_id')->constrained('coupons')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_applied_discounts', function (Blueprint $table) {
            if (Schema::hasColumn('pos_applied_discounts', 'coupon_name')) {
                $table->dropColumn('coupon_name');
            }

            if (Schema::hasColumn('pos_applied_discounts', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
        });
    }
};
