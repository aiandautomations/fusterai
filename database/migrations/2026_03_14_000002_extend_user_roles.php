<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL CHECK constraint approach — cleanest way to extend an enum
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20)");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin','admin','manager','agent'))");
    }

    public function down(): void
    {
        // Downgrade any extended roles back to 'agent' before restoring the constraint
        DB::statement("UPDATE users SET role = 'agent' WHERE role IN ('super_admin','manager')");
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(10)");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','agent'))");
    }
};
