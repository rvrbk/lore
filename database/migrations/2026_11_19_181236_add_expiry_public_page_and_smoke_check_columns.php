<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('expiry_checked_at')->nullable();
            $table->string('public_token', 32)->nullable()->unique();
        });
        Schema::table('deployments', function (Blueprint $table) {
            $table->string('smoke_status')->nullable();
            $table->string('smoke_detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['ssl_expires_at', 'domain_expires_at', 'expiry_checked_at', 'public_token']);
        });
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['smoke_status', 'smoke_detail']);
        });
    }
};
