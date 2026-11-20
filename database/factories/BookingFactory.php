<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(3)->startOfHour();

        return [
            'booking_type_id' => BookingType::factory(),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_timezone' => 'America/New_York',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'token' => Str::random(40),
        ];
    }
}
