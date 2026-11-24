<?php

namespace Database\Factories;

use App\Models\CalendarSource;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'name' => fake()->word(),
            'url' => 'https://calendar.example.com/'.fake()->uuid().'.ics',
            'color' => '#818cf8',
        ];
    }
}
