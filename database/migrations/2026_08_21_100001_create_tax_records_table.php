<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('transaction_no', 50);
            $table->date('tax_date');
            $table->foreignUuid('tax_rate_id')->nullable();
            $table->enum('tax_type', ['ppn_keluaran', 'ppn_masukan', 'pph_final_umkm', 'pph', 'other']);
            $table->string('tax_name', 120);
            $table->decimal('tax_base', 18, 2);
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_amount', 18, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('tax_account_id')->nullable();
            $table->foreignUuid('counter_account_id')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'transaction_no']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'tax_type']);
            $table->index(['store_id', 'tax_date']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            $table->foreign('tax_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('counter_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_records');
    }
};
