<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('heat_key', 40)->nullable()->unique();
        });

        foreach (DB::table('monitors')->whereNull('heat_key')->pluck('id') as $id) {
            DB::table('monitors')->where('id', $id)->update(['heat_key' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['heat_key']);
            $table->dropColumn('heat_key');
        });
    }
};
