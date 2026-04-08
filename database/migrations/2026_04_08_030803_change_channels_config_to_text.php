<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // config is encrypted by the Channel model (Crypt::encryptString) so it
        // must be stored as text, not jsonb.
        Schema::table('channels', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->jsonb('config')->nullable()->change();
        });
    }
};
