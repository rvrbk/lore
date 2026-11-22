<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedSmallInteger('x');
            $table->unsignedInteger('y');
            $table->string('device', 10);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['monitor_id', 'path', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_clicks');
    }
};
