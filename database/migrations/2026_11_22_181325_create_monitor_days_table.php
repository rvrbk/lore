<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('checks');
            $table->unsignedInteger('up_checks');
            $table->unique(['monitor_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_days');
    }
};
