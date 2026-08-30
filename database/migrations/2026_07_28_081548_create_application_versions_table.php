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
        Schema::create('application_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->date('release_date');
            $table->text('release_notes')->nullable();
            $table->string('minimum_version', 20)->nullable();
            $table->boolean('is_latest')->default(false);
            $table->timestamps();

            $table->index('version');
            $table->index('is_latest');
            $table->index('release_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_versions');
    }
};
