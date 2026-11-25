<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<VaultItem>
 */
class VaultItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => Auth::id() ?? User::factory(),
            'name' => fake()->unique()->company(),
            'url' => fake()->url(),
            'username' => fake()->userName(),
            'password' => fake()->password(16, 20),
            'notes' => null,
        ];
    }
}
