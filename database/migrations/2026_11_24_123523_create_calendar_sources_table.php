<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->string('color', 7)->default('#818cf8');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sources');
    }
};
