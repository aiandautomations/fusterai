<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_statuses', function (Blueprint $table) {
            // Tracks when the SLA clock was paused (e.g. conversation set to pending).
            $table->timestamp('paused_at')->nullable()->after('resolution_breached');
            // Accumulated pause time in minutes from previous pause/resume cycles.
            $table->unsignedInteger('pause_offset_minutes')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('sla_statuses', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'pause_offset_minutes']);
        });
    }
};
