<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorldClock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<WorldClock>
 */
class WorldClockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timezone = fake()->unique()->randomElement(['Europe/Amsterdam', 'Asia/Tokyo', 'America/New_York', 'Africa/Kampala', 'Australia/Sydney']);

        return ['user_id' => Auth::id() ?? User::factory(), 'city' => WorldClock::cities()[$timezone], 'timezone' => $timezone];
    }
}
