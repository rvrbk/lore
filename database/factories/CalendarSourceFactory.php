<?php

namespace Database\Factories;

use App\Models\CalendarSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<CalendarSource>
 */
class CalendarSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => fake()->word(),
            'url' => 'https://calendar.example.com/'.fake()->uuid().'.ics',
            'color' => '#818cf8',
        ];
    }
}
