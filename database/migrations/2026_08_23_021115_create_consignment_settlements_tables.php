<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('settlement_code', 30);
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('supplier_payable', 15, 2)->default(0);
            $table->decimal('store_margin', 15, 2)->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference_no', 100)->nullable();
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->char('request_uuid', 36)->nullable();
            $table->foreignId('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'settlement_code'], 'uq_cst_store_code');
            $table->unique(['store_id', 'request_uuid'], 'uq_cst_store_uuid');
            $table->index(['store_id', 'supplier_id', 'status'], 'idx_cst_supplier');
            $table->index(['store_id', 'period_start', 'period_end'], 'idx_cst_period');
        });

        Schema::create('consignment_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('consignment_settlements')->cascadeOnDelete();
            $table->foreignId('sales_allocation_id')->constrained('consignment_sales_allocations');
            $table->decimal('quantity_sold', 15, 2);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('supplier_amount', 15, 2);
            $table->decimal('store_margin', 15, 2);
            $table->timestamps();

            $table->index('settlement_id', 'idx_csi_settlement');
            $table->unique('sales_allocation_id', 'uq_csi_allocation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_settlement_items');
        Schema::dropIfExists('consignment_settlements');
    }
};