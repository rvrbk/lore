<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each person's own public key: their encrypted vault exports are locked to it, so only they can open them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('vault_public_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vault_public_key');
        });
    }
};
