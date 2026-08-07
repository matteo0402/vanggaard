<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Credit;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credit>
 */
class CreditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'artist_id' => Artist::factory(),
            'position' => 0,
            'role' => fake()->randomElement(['Producer', 'Written-By', 'Engineer']),
            'credited_name' => fake()->optional()->name(),
            'join_text' => '',
            'retired_at' => null,
        ];
    }
}
