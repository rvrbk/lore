<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('world_clocks', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('timezone');
        });

        DB::table('world_clocks')->update(['position' => DB::raw('id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('world_clocks', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
