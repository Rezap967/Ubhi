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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('status', ['Draft', 'Dikirim', 'Sebagian', 'Diterima', 'Dibatalkan'])->default('Draft');
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->enum('jenis_pembayaran', ['Kredit', 'Tunai']);
            $table->date('estimasi_tiba')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('store_id');
            $table->index('po_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
