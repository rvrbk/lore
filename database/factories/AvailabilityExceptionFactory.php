<?php

namespace Database\Factories;

use App\Models\AvailabilityException;
use App\Models\BookingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityException>
 */
class AvailabilityExceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_type_id' => BookingType::factory(),
            'user_id' => fn (array $attributes) => BookingType::withoutGlobalScopes()->find($attributes['booking_type_id'])?->user_id,
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'note' => null,
        ];
    }
}
