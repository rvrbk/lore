<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_scout_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('new_matches');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_scout_snapshots');
    }
};
