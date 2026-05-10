<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // GIN index on description using pg_trgm for fast ILIKE '%term%' search.
        // Requires the pg_trgm extension (bundled with PostgreSQL, no extra install needed).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS activity_log_description_trgm_idx ON activity_log USING gin (description gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS activity_log_log_name_trgm_idx ON activity_log USING gin (log_name gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS activity_log_description_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS activity_log_log_name_trgm_idx');
    }
};
