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
        Schema::create('supplier_payable_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('supplier_payable_id')->index();
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

            // Indexes
            $table->unique(['store_id', 'payment_no']);
            $table->index(['store_id', 'payment_date']);

            // Foreign keys
            $table->foreign('cash_or_bank_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payable_payments');
    }
};
