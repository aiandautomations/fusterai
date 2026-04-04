<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Prevents duplicate conversations when the same inbound email is
            // processed more than once (e.g. duplicate IMAP fetch or retry).
            // Only applies when channel_id is set — nullable rows are excluded.
            $table->unique(['mailbox_id', 'channel_id'], 'conversations_mailbox_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_mailbox_channel_unique');
        });
    }
};
