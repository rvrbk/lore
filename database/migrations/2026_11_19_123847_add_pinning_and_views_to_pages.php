<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dateTime('pinned_at')->nullable();
            $table->dateTime('viewed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['viewed_at']);
            $table->dropColumn(['pinned_at', 'viewed_at']);
        });
    }
};
