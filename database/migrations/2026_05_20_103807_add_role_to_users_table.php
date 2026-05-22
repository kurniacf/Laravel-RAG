<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya dua role: 'user' (default saat register) dan 'admin'.
        // SQLite (test) tidak mendukung CHECK constraint dengan baik via
        // Schema::enum, jadi kita pakai string + validasi di layer aplikasi
        // dan CHECK constraint khusus di PostgreSQL.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('user')->after('password');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
