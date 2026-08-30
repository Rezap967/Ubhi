<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('pos_transactions')
                ->cascadeOnDelete();

            $table->string('receipt_number', 40)->unique();
            $table->json('receipt_payload')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('printed_at')->nullable();

            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipts');
    }
};
