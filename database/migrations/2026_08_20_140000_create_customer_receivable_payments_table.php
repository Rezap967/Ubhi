<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receivable_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('customer_receivable_id')->index();
            $table->string('payment_no', 50);
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignUuid('cash_or_bank_account_id');
            $table->enum('payment_method', ['cash', 'bank', 'transfer', 'ewallet', 'other'])->default('cash');
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'payment_no']);
            $table->index(['store_id', 'payment_date']);
            $table->foreign('cash_or_bank_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receivable_payments');
    }
};
