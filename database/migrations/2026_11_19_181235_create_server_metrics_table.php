<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->index();
            $table->decimal('disk_used_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('disk_free_bytes')->nullable();
            $table->decimal('memory_used_percent', 5, 2)->nullable();
            $table->decimal('load_average', 6, 2)->nullable();
            $table->unsignedInteger('queue_depth')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
