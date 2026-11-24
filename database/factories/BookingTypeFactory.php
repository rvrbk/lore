<?php

namespace Database\Factories;

use App\Models\BookingType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<BookingType>
 */
class BookingTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'duration_minutes' => 30,
            'timezone' => 'Europe/Amsterdam',
            'days' => [1, 2, 3, 4, 5],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'buffer_minutes' => 0,
            'min_notice_hours' => 4,
            'max_days_ahead' => 30,
            'is_active' => true,
        ];
    }
}
