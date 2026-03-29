<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            $table->unsignedInteger('first_response_minutes'); // target first response time
            $table->unsignedInteger('resolution_minutes');     // target resolution time
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'priority']);
        });

        Schema::create('sla_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_achieved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('first_response_breached')->default(false);
            $table->boolean('resolution_breached')->default(false);
            $table->timestamps();

            $table->unique('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_statuses');
        Schema::dropIfExists('sla_policies');
    }
};
