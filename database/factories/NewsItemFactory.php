<?php

namespace Database\Factories;

use App\Models\NewsItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsItem>
 */
class NewsItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->sha1(),
            'title' => fake()->sentence(8),
            'source_name' => fake()->company(),
            'region' => 'netherlands',
            'category' => 'Travel',
            'url' => fake()->url(),
            'relevance' => fake()->numberBetween(5, 10),
            'is_alert' => false,
            'published_at' => now()->subHour(),
        ];
    }

    public function alert(): static
    {
        return $this->state(fn () => ['is_alert' => true, 'relevance' => 10]);
    }
}
