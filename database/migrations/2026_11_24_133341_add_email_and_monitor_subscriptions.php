<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts get an optional email (only used to reset a forgotten password), and users subscribe to the monitors whose
     * domain expiry reminders they want. The first account, which used to receive all of them, stays subscribed to every monitor.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('monitor_user', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['monitor_id', 'user_id']);
        });

        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null) {
            DB::table('monitors')->pluck('id')->each(fn (int $monitorId) => DB::table('monitor_user')->insert(['monitor_id' => $monitorId, 'user_id' => $firstUser]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_user');
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
