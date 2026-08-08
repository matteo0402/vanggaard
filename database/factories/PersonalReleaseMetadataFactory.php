<?php

namespace Database\Factories;

use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalReleaseMetadata>
 */
class PersonalReleaseMetadataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'release_id' => Release::factory(),
            'personal_notes' => fake()->optional()->paragraph(),
            'rating' => fake()->optional()->numberBetween(1, 5),
            'corrected_year' => null,
            'is_year_approximate' => false,
            'is_favourite' => false,
            'is_dj_ready' => false,
            'energy' => null,
            'bpm' => null,
        ];
    }
}
