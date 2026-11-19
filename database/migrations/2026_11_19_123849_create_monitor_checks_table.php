<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_up');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();
            $table->dateTime('checked_at');

            $table->index(['monitor_id', 'checked_at']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->dateTime('alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['consecutive_failures', 'alerted_at']);
        });
        Schema::dropIfExists('monitor_checks');
    }
};
