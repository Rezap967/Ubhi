<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_carts', 'subtotal_amount')) {
                $table->decimal('subtotal_amount', 15, 2)->default(0)->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            if (Schema::hasColumn('pos_carts', 'subtotal_amount')) {
                $table->dropColumn('subtotal_amount');
            }
        });
    }
};
