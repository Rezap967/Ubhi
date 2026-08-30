<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->enum('target_type', ['produk', 'kategori', 'brand', 'grup_pelanggan']);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['promotion_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_targets');
    }
};
