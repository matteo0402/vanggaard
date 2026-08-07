<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
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
            'uri' => 'https://www.youtube.com/watch?v='.fake()->unique()->regexify('[A-Za-z0-9]{11}'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'duration' => fake()->numberBetween(30, 600),
            'embed' => true,
            'retired_at' => null,
        ];
    }
}
