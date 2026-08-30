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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->onDelete('set null');
            $table->string('code', 20);
            $table->string('name', 255);
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->unique(['store_id', 'code'], 'idx_cust_store_code');
            $table->index(['store_id', 'deleted_at'], 'idx_cust_store_deleted');
            $table->index(['store_id', 'status', 'deleted_at'], 'idx_cust_store_status');
            $table->index('customer_group_id', 'idx_cust_group');
            $table->unique(['store_id', 'phone'], 'idx_cust_phone_active');
            $table->unique(['store_id', 'email'], 'idx_cust_email_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
