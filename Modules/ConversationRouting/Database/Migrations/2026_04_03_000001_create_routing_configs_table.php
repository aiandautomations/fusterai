<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // null mailbox_id = workspace-level fallback
            $table->foreignId('mailbox_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('mode', ['round_robin', 'least_loaded'])->default('round_robin');
            $table->boolean('active')->default(true);
            // Tracks which user was last assigned (round-robin pointer)
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'mailbox_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_configs');
    }
};
