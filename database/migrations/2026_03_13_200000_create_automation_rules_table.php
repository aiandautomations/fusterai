<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->string('trigger');         // conversation.created | conversation.replied | conversation.closed | conversation.assigned | time.idle
            $table->json('conditions');        // [['field' => 'priority', 'operator' => 'equals', 'value' => 'high'], ...]
            $table->json('actions');           // [['type' => 'assign_to', 'value' => 1], ['type' => 'set_priority', 'value' => 'urgent'], ...]
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'trigger', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
