<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
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
            'master_discogs_id' => fake()->optional()->numberBetween(1, 1_000_000),
            'title' => fake()->sentence(3),
            'country' => fake()->countryCode(),
            'released_year' => fake()->numberBetween(1950, 2026),
            'released' => fake()->date(),
            'notes' => fake()->optional()->paragraph(),
            'data_quality' => 'Correct',
            'source_url' => "https://www.discogs.com/release/{$discogsId}",
            'fetched_at' => now(),
            'discogs_changed_at' => fake()->optional()->dateTimeBetween('-1 year'),
            'source_hash' => hash('sha256', fake()->uuid()),
            'image_urls' => [],
        ];
    }
}
