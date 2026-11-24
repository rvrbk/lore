<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users get a role. The first account, the owner of this Lore, becomes its admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user');
        });

        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null) {
            DB::table('users')->where('id', $firstUser)->update(['role' => 'admin']);
            DB::table('monitors')->pluck('id')->each(fn (int $monitorId) => DB::table('monitor_user')->insertOrIgnore(['monitor_id' => $monitorId, 'user_id' => $firstUser]));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
