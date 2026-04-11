<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_views');
    }
};
