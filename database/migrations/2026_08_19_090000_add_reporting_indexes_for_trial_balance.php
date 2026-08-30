<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(['store_id', 'status', 'journal_date'], 'journal_entries_trial_balance_idx');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->index(['store_id', 'type', 'is_active'], 'accounts_trial_balance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_entries_trial_balance_idx');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropIndex('accounts_trial_balance_idx');
        });
    }
};
