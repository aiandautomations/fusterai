<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at');

            $table->primary(['user_id', 'conversation_id']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
    }
};
