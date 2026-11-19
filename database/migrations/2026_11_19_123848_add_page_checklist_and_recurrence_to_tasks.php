<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->json('checklist')->nullable();
            $table->string('recurrence')->default('none');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
            $table->dropColumn(['checklist', 'recurrence']);
        });
    }
};
