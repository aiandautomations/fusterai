<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('meta');
            $table->timestamp('opened_at')->nullable()->after('tracking_token');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn(['tracking_token', 'opened_at']);
        });
    }
};
