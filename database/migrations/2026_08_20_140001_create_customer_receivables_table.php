<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receivables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('receivable_no', 50);
            $table->foreignId('customer_id')->nullable();
            $table->string('customer_name', 150);
            $table->date('receivable_date');
            $table->date('due_date');
            $table->string('description', 255)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('receivable_account_id');
            $table->foreignUuid('revenue_account_id');
            $table->decimal('original_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->enum('status', ['open', 'partial', 'paid', 'overdue', 'void'])->default('open');
            $table->foreignUuid('journal_entry_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'receivable_no']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'due_date']);
            $table->index(['store_id', 'customer_name']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('receivable_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('revenue_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('customer_receivable_payments', function (Blueprint $table): void {
            $table->foreign('customer_receivable_id')->references('id')->on('customer_receivables')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_receivable_payments', function (Blueprint $table): void {
            $table->dropForeign(['customer_receivable_id']);
        });

        Schema::dropIfExists('customer_receivables');
    }
};
