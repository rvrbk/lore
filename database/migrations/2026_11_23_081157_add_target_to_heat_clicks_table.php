<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heat_clicks', function (Blueprint $table) {
            $table->string('element', 12)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('href', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('heat_clicks', function (Blueprint $table) {
            $table->dropColumn(['element', 'label', 'href']);
        });
    }
};
