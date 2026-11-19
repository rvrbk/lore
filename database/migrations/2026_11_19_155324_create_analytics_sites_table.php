<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('property_id')->unique();
            $table->timestamps();
        });

        // Carry over sites that were configured through GA_PROPERTIES.
        foreach (array_filter(array_map('trim', explode(',', (string) config('services.google_analytics.properties')))) as $pair) {
            [$name, $id] = array_pad(explode(':', $pair, 2), 2, '');
            if (ctype_digit(trim($id)) && trim($name) !== '') {
                DB::table('analytics_sites')->insertOrIgnore(['name' => trim($name), 'property_id' => trim($id), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sites');
    }
};
