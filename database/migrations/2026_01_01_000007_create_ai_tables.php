<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['reply', 'summary', 'categorization'])->default('reply');
            $table->text('content');
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->boolean('accepted')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'type']);
        });

        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('kb_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('source_url')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        // Channels (beyond email — chat, whatsapp, slack, etc.)
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('type');  // email|chat|whatsapp|slack|api|sms
            $table->string('name')->nullable();
            $table->jsonb('config')->nullable();  // encrypted at model level
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
        Schema::dropIfExists('kb_documents');
        Schema::dropIfExists('knowledge_bases');
        Schema::dropIfExists('ai_suggestions');
    }
};
