<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 50)->unique(); // Unique coupon code
            $table->string('name', 255)->nullable();
            $table->enum('discount_type', ['persentase', 'nominal']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 15, 2)->nullable(); // Cap for percentage discounts
            $table->decimal('min_purchase', 15, 2)->nullable(); // Minimum purchase requirement
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('total_quota')->nullable(); // null = unlimited
            $table->unsignedInteger('used_count')->default(0); // Increment on checkout success
            $table->unsignedInteger('per_customer_limit')->default(1); // Max usage per customer
            $table->enum('status', ['aktif', 'nonaktif', 'berakhir'])->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['store_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('code'); // For fast coupon lookup by code
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
