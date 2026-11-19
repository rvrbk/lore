<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 2000);
            $table->boolean('is_up')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->dateTime('status_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
