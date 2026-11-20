<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('title', 300);
            $table->string('source_name', 100)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('url', 2000)->nullable();
            $table->unsignedTinyInteger('relevance')->default(0);
            $table->boolean('is_alert')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
