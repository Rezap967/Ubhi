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
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('device_id', 100)->index();
            $table->enum('status', ['open', 'closed', 'force_closed'])->default('open')->index();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->timestamp('opened_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('user_id');
            $table->index(['store_id', 'user_id', 'device_id', 'status'], 'pos_sessions_store_user_device_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
