<?php

namespace Database\Factories;

use App\Models\PointySite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointySite>
 */
class PointySiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'key' => fake()->unique()->slug(2), 'css' => null];
    }
}
