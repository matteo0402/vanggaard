<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
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
            'sequence' => 0,
            'position' => 'A1',
            'title' => fake()->sentence(3),
            'duration' => fake()->time('i:s'),
            'type' => 'track',
            'retired_at' => null,
        ];
    }
}
