<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['message', 'note', 'activity', 'ai_suggestion'])->default('message');
            $table->text('body');
            $table->text('body_plain')->nullable();
            $table->string('source')->default('email'); // email|chat|whatsapp|slack|api
            $table->string('status')->default('received'); // received|sent|draft|note
            $table->jsonb('meta')->nullable();          // cc, bcc, headers, message-id, etc.
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'type']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('threads');
    }
};
