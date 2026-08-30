<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_expense_generations')) {
            return;
        }

        DB::statement('ALTER TABLE recurring_expense_generations MODIFY expense_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');

        if (! $this->constraintExists('recurring_expense_generations', 'rec_exp_gen_expense_fk')) {
            Schema::table('recurring_expense_generations', function (Blueprint $table): void {
                $table->foreign('expense_id', 'rec_exp_gen_expense_fk')
                    ->references('id')
                    ->on('expenses')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->indexExists('recurring_expense_generations', 'rec_exp_gen_period_unique')) {
            Schema::table('recurring_expense_generations', function (Blueprint $table): void {
                $table->unique(['recurring_expense_id', 'period_key'], 'rec_exp_gen_period_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('recurring_expense_generations')) {
            return;
        }

        if ($this->constraintExists('recurring_expense_generations', 'rec_exp_gen_expense_fk')) {
            Schema::table('recurring_expense_generations', function (Blueprint $table): void {
                $table->dropForeign('rec_exp_gen_expense_fk');
            });
        }
    }

    protected function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    protected function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
