<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable()
                ->after('id')
                ->constrained('stores')
                ->restrictOnDelete();
        });

        DB::table('warehouses')
            ->whereNull('store_id')
            ->update(['store_id' => 1]);

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
