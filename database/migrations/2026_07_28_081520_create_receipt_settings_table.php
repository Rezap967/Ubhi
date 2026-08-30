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
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_tax')->default(true);
            $table->boolean('show_discount')->default(true);
            $table->boolean('show_cashier_name')->default(true);
            $table->string('tax_label', 50)->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};
