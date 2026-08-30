<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_stock', 15, 2)
                ->default(0)
                ->after('initial_stock')
                ->comment('Batas minimal stok untuk peringatan low stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('minimum_stock');
        });
    }
};
