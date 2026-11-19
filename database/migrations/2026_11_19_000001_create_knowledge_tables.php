<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->dateTime('due_at');
            $table->string('recurrence')->default('none');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['completed_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('spaces');
    }
};
