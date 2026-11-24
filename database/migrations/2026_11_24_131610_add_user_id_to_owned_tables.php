<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows belong to one user. */
    private const TABLES = ['calendar_sources', 'reminders', 'booking_types', 'availability_exceptions', 'bookings', 'world_clocks', 'job_scout_snapshots'];

    /**
     * Everything that exists today belongs to the first account, which until now was the only one that could be made.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->string('job_scout_email')->nullable();
            $blueprint->string('telegram_chat_id')->nullable()->unique();
            $blueprint->string('telegram_link_code')->nullable();
        });
        // The Job Scout account that used to be set for the whole installation now belongs to the first user.
        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null && filled(config('services.job_scout.email'))) {
            DB::table('users')->where('id', $firstUser)->update(['job_scout_email' => config('services.job_scout.email')]);
        }
        // Likewise the Telegram chat that used to be set for the whole installation.
        if ($firstUser !== null && filled(config('services.telegram.chat_id'))) {
            DB::table('users')->where('id', $firstUser)->update(['telegram_chat_id' => (string) config('services.telegram.chat_id')]);
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            });

            $owner = DB::table('users')->orderBy('id')->value('id');
            if ($owner !== null) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $owner]);
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable(false)->change();
            });
        }

        Schema::table('job_scout_snapshots', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['day']);
            $blueprint->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::table('job_scout_snapshots', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['user_id', 'day']);
            $blueprint->unique('day');
        });
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['telegram_chat_id']);
            $blueprint->dropColumn(['job_scout_email', 'telegram_chat_id', 'telegram_link_code']);
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('user_id');
            });
        }
    }
};
