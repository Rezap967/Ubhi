<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_payables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('payable_no', 50);
            $table->foreignId('supplier_id')->nullable();
            $table->string('supplier_name', 150);
            $table->date('payable_date');
            $table->date('due_date')->index();
            $table->string('description', 255)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('expense_or_inventory_account_id');
            $table->foreignUuid('liability_account_id');
            $table->decimal('original_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->enum('status', ['open', 'partial', 'paid', 'overdue', 'void'])->default('open')->index();
            $table->foreignUuid('journal_entry_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['store_id', 'payable_no']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'due_date']);
            $table->index(['store_id', 'supplier_name']);
            $table->index(['source_type', 'source_id']);

            // Foreign keys
            $table->foreign('expense_or_inventory_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('liability_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();

            // Supplier foreign key is added in the follow-up migration so existing databases can be upgraded safely.
        });

        Schema::table('supplier_payable_payments', function (Blueprint $table): void {
            $table->foreign('supplier_payable_id')->references('id')->on('supplier_payables')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_payable_payments', function (Blueprint $table): void {
            $table->dropForeign(['supplier_payable_id']);
        });
        Schema::dropIfExists('supplier_payables');
    }
};
