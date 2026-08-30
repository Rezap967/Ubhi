<?php

// database/migrations/2026_07_24_000001_create_roles_and_stores_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('role_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('created_by');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['is_active', 'last_login_at', 'deleted_at']);
        });

        Schema::dropIfExists('roles');
    }
};
