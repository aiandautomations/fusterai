<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->string('icon', 50)->default('folder');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
