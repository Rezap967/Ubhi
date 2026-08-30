<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_sessions', function (Blueprint $table) {
            // Ubah default status menjadi 'draft' agar mendukung alur draft → selesai / dibatalkan
            $table->string('status', 20)->default('draft')->change();

            // Kolom alasan pembatalan (diisi saat status = dibatalkan)
            $table->text('cancel_reason')->nullable()->after('notes');

            // Indeks untuk filtering by status
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('selesai')->change();
            $table->dropColumn('cancel_reason');
            $table->dropIndex(['store_id', 'status']);
        });
    }
};
