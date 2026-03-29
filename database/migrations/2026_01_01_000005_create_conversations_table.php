<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->unique(['workspace_id', 'name']);
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'pending', 'closed', 'spam'])->default('open')->index();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('subject');
            $table->string('channel_type')->default('email');
            $table->string('channel_id')->nullable();   // external ID (e.g. message-id for email)
            $table->text('ai_summary')->nullable();
            $table->jsonb('ai_tags')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'last_reply_at']);
            $table->index(['mailbox_id', 'status']);
            $table->index('assigned_user_id');
        });

        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['conversation_id', 'tag_id']);
        });

        Schema::create('followers', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followers');
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('tags');
    }
};
