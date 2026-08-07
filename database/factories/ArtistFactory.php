<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discogsId = fake()->unique()->numberBetween(1, 10_000_000);

        return [
            'discogs_id' => $discogsId,
            'name' => fake()->name(),
            'real_name' => fake()->optional()->name(),
            'profile' => fake()->optional()->paragraph(),
            'source_url' => "https://www.discogs.com/artist/{$discogsId}",
            'fetched_at' => now(),
        ];
    }
}
