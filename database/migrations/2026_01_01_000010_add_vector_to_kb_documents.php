<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'pgsql') {
            Schema::ensureVectorExtensionExists();

            Schema::table('kb_documents', function (Blueprint $table) {
                $table->vector('embedding', dimensions: 1536)->nullable()->after('content');
            });
        } else {
            // SQLite / test: store as JSON text column
            Schema::table('kb_documents', function (Blueprint $table) {
                $table->json('embedding')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
