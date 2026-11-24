<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu visits become per user; the old global counts are discarded.
     */
    public function up(): void
    {
        DB::table('nav_visits')->delete();

        Schema::table('nav_visits', function (Blueprint $table) {
            $table->dropUnique(['item']);
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'item']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('nav_visits')->delete();

        Schema::table('nav_visits', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'item']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('item');
        });
    }
};
