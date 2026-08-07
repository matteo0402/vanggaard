<?php

namespace Database\Factories;

use App\Models\Format;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Format>
 */
class FormatFactory extends Factory
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
            'position' => 0,
            'name' => 'Vinyl',
            'quantity' => 1,
            'text' => fake()->optional()->randomElement(['LP', '7\"', '12\"']),
            'descriptions' => ['Album'],
            'retired_at' => null,
        ];
    }
}
