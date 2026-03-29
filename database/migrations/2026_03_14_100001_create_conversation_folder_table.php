<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_folder', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->primary(['conversation_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_folder');
    }
};
