<?php

namespace Database\Factories;

use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscogsCollectionInstance>
 */
class DiscogsCollectionInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discogsInstanceId = fake()->unique()->numberBetween(1, 10_000_000);

        return [
            'discogs_account_id' => DiscogsAccount::factory(),
            'user_id' => fn (array $attributes): int => DiscogsAccount::query()
                ->whereKey($attributes['discogs_account_id'])
                ->firstOrFail()
                ->user_id,
            'collection_item_id' => function (array $attributes): int {
                return CollectionItem::factory()->create(['user_id' => $attributes['user_id']])->id;
            },
            'last_seen_sync_run_id' => null,
            'missing_confirmed_sync_run_id' => null,
            'discogs_instance_id' => $discogsInstanceId,
            'discogs_folder_id' => fake()->optional()->numberBetween(1, 1000),
            'source_url' => fn (array $attributes): string => CollectionItem::query()
                ->whereKey($attributes['collection_item_id'])
                ->firstOrFail()
                ->release
                ->source_url,
            'fetched_at' => now(),
        ];
    }
}
