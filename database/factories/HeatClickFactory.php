<?php

namespace Database\Factories;

use App\Models\HeatClick;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeatClick>
 */
class HeatClickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'path' => '/',
            'x' => fake()->numberBetween(0, HeatClick::X_SCALE),
            'y' => fake()->numberBetween(0, 3000),
            'device' => 'desktop',
        ];
    }
}
