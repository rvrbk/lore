<?php

namespace Database\Factories;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => Incident::DOWN,
            'title' => fake()->word().' is down',
            'started_at' => now()->subMinutes(10),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }
}
