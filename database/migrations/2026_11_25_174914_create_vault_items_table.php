<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password vault. Name and URL stay readable so they can be searched; the secret columns are text because
     * the encrypted cast stores ciphertext, which is longer than what was typed.
     */
    public function up(): void
    {
        Schema::create('vault_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->text('username')->nullable();
            $table->text('password');
            $table->text('notes')->nullable();
            $table->timestamp('last_revealed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_items');
    }
};
