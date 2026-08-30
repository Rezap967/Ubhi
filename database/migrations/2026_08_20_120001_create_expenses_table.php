<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('expense_no', 50);
            $table->date('expense_date');
            $table->string('description', 255);
            $table->foreignUuid('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignUuid('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('cash_or_bank_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->uuid('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);
            $table->enum('payment_method', ['cash', 'bank', 'transfer', 'ewallet', 'other'])->default('cash');
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'expense_no']);
            $table->index(['store_id', 'expense_date']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
