<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN type ENUM(
            'qris',
            'bank_transfer',
            'e_wallet',
            'debit_card',
            'credit_card',
            'cash'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN type ENUM(
            'qris',
            'bank_transfer',
            'e_wallet',
            'debit_card',
            'credit_card'
        ) NOT NULL");
    }
};