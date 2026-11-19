<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
