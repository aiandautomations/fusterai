<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('signature')->nullable();
            $table->jsonb('imap_config')->nullable();   // encrypted at model level
            $table->jsonb('smtp_config')->nullable();   // encrypted at model level
            $table->jsonb('auto_reply_config')->nullable();
            $table->jsonb('ai_config')->nullable();
            $table->string('channel_type')->default('email');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Pivot: users assigned to mailboxes
        Schema::create('mailbox_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->jsonb('permissions')->nullable();
            $table->unique(['mailbox_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_user');
        Schema::dropIfExists('mailboxes');
    }
};
