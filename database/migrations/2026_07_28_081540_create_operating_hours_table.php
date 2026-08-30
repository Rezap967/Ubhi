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
        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('day_name', 10);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
            $table->index('store_id');
            $table->index('day_of_week');
            $table->index('is_open');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_hours');
    }
};
