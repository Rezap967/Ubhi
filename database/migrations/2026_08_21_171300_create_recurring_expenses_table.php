<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurring_expenses')) {
            DB::statement('ALTER TABLE recurring_expenses MODIFY expense_category_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
            if (! $this->constraintExists('recurring_expenses', 'rec_expense_category_fk')) {
                Schema::table('recurring_expenses', function (Blueprint $table): void {
                    $table->foreign('expense_category_id', 'rec_expense_category_fk')
                        ->references('id')
                        ->on('expense_categories');
                });
            }
            if (! $this->indexExists('recurring_expenses', 'rec_expenses_store_category_idx')) {
                Schema::table('recurring_expenses', function (Blueprint $table): void {
                    $table->index(['store_id', 'expense_category_id'], 'rec_expenses_store_category_idx');
                });
            }

            $this->createGenerationsTable();

            return;
        }

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('recurring_code', 40);
            $table->string('name', 150);


            $table->foreignUuid('expense_category_id');
            $table->foreign('expense_category_id', 'rec_expense_category_fk')
                ->references('id')
                ->on('expense_categories');

            $table->decimal('amount', 18, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('payment_method', ['cash', 'transfer', 'debit', 'kredit', 'ewallet']);
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->date('next_due_date');
            $table->timestamp('last_generated_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['store_id', 'recurring_code']);
            $table->index(['store_id', 'status', 'next_due_date']);
            $table->index(['store_id', 'expense_category_id']);
        });

        $this->createGenerationsTable();
    }

    protected function createGenerationsTable(): void
    {
        if (Schema::hasTable('recurring_expense_generations')) {
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

            return;
        }

        Schema::create('recurring_expense_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('recurring_expense_id')->constrained('recurring_expenses')->cascadeOnDelete();
            $table->string('period_key', 16);
            $table->foreignUuid('expense_id');
            $table->foreign('expense_id', 'rec_exp_gen_expense_fk')->references('id')->on('expenses')->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users');

           $table->unique(['recurring_expense_id', 'period_key'], 'uq_rec_exp_gen_period');
        });
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

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_generations');
        Schema::dropIfExists('recurring_expenses');
    }
};
