<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_types', function (Blueprint $table) {
            $table->boolean('include_meet')->default(true);
            $table->string('meet_url')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('meeting_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_types', function (Blueprint $table) {
            $table->dropColumn(['include_meet', 'meet_url']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('meeting_url');
        });
    }
};
