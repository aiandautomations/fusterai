<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('canned_responses', function (Blueprint $table) {
            $table->foreignId('mailbox_id')->nullable()->after('workspace_id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('canned_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailbox_id');
        });
    }
};
