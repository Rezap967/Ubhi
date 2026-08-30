<?php

// database/migrations/2026_08_01_000001_create_warehouses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('warehouse_type', 50)->default('store_stock');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('code');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
