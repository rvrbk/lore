<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('deploy_path')->nullable();
        });
        Schema::table('deployments', function (Blueprint $table) {
            $table->string('site')->nullable();
            $table->string('path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('deploy_path');
        });
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['site', 'path']);
        });
    }
};
