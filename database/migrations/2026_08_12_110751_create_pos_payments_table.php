<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('pos_transactions')
                ->cascadeOnDelete();

            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_method', 50);
            $table->decimal('amount', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('reference_number', 100)->nullable();
            $table->string('provider', 100)->nullable();
            $table->json('metadata')->nullable();

            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'voided'])->default('paid');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('transaction_id');
            $table->index('payment_method_id');
            $table->index('payment_method');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
