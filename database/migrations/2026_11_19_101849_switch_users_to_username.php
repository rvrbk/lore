<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts sign in with a username and password only; no email is stored.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('email', 'username');
            $table->dropColumn(['name', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('username', 'email');
            $table->string('name')->default('');
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
