<?php

namespace Database\Factories;

use App\Models\Deployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch' => 'main',
            'status' => 'succeeded',
            'from_commit' => fake()->sha1(),
            'to_commit' => fake()->sha1(),
            'output' => 'ok',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
