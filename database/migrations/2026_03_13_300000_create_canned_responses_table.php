<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');          // short title / search keyword
            $table->text('content');         // the response body
            $table->timestamps();
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_responses');
    }
};
