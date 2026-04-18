<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_statuses', function (Blueprint $table) {
            if (! Schema::hasColumn('sla_statuses', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('resolution_breached');
            }
            if (! Schema::hasColumn('sla_statuses', 'pause_offset_minutes')) {
                $table->unsignedInteger('pause_offset_minutes')->default(0)->after('paused_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sla_statuses', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'pause_offset_minutes']);
        });
    }
};
