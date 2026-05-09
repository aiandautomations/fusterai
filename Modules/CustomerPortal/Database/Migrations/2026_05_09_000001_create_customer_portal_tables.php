<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->rememberToken()->after('is_blocked');
        });

        Schema::create('portal_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['workspace_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_login_tokens');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
