<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('rating', ['good', 'bad']);
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique('conversation_id'); // one response per conversation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
