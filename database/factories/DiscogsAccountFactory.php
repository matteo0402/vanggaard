<?php

namespace Database\Factories;

use App\Models\DiscogsAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscogsAccount>
 */
class DiscogsAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'user_id' => User::factory(),
            'username' => $username,
            'personal_access_token' => fake()->sha256(),
            'source_url' => "https://www.discogs.com/user/{$username}",
            'fetched_at' => now(),
        ];
    }
}
