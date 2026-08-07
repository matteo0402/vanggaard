<?php

namespace Database\Factories;

use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
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
            'name' => fake()->company(),
            'profile' => fake()->optional()->paragraph(),
            'contact_info' => fake()->optional()->address(),
            'source_url' => "https://www.discogs.com/label/{$discogsId}",
            'fetched_at' => now(),
        ];
    }
}
