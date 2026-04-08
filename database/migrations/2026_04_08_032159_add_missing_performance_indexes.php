<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users.workspace_id — queried on every page load to scope agents/mailboxes
        Schema::table('users', function (Blueprint $table) {
            $table->index('workspace_id', 'users_workspace_id_idx');
        });

        // customers.workspace_id — every customer query is workspace-scoped
        Schema::table('customers', function (Blueprint $table) {
            $table->index('workspace_id', 'customers_workspace_id_idx');
        });

        // threads.user_id / customer_id — queried when building customer history
        Schema::table('threads', function (Blueprint $table) {
            $table->index('user_id',      'threads_user_id_idx');
            $table->index('customer_id',  'threads_customer_id_idx');
        });

        // conversation_folder.folder_id — needed for whereHas('folders') filter
        Schema::table('conversation_folder', function (Blueprint $table) {
            $table->index('folder_id', 'conversation_folder_folder_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users',               fn ($t) => $t->dropIndex('users_workspace_id_idx'));
        Schema::table('customers',           fn ($t) => $t->dropIndex('customers_workspace_id_idx'));
        Schema::table('threads',             fn ($t) => $t->dropIndex('threads_user_id_idx'));
        Schema::table('threads',             fn ($t) => $t->dropIndex('threads_customer_id_idx'));
        Schema::table('conversation_folder', fn ($t) => $t->dropIndex('conversation_folder_folder_id_idx'));
    }
};
