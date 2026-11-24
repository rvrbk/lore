<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The morning digest on Telegram becomes something users subscribe to. The first account, which used to get it, stays subscribed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_subscribed')->default(false);
        });

        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null) {
            DB::table('users')->where('id', $firstUser)->update(['digest_subscribed' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('digest_subscribed');
        });
    }
};
