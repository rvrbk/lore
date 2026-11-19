<?php

namespace Database\Factories;

use App\Models\AnalyticsSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsSite>
 */
class AnalyticsSiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->domainWord(),
            'property_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
        ];
    }
}
