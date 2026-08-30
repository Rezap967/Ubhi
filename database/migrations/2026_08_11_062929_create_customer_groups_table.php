<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['persen', 'nominal'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['store_id', 'name'], 'idx_cg_store_name');
            $table->index(['store_id', 'deleted_at'], 'idx_cg_store_deleted');

            $table->foreign('store_id')->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
