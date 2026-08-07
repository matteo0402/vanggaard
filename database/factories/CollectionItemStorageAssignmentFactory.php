<?php

namespace Database\Factories;

use App\Models\CollectionItem;
use App\Models\CollectionItemStorageAssignment;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionItemStorageAssignment>
 */
class CollectionItemStorageAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_item_id' => CollectionItem::factory(),
            'user_id' => fn (array $attributes): int => CollectionItem::query()
                ->whereKey($attributes['collection_item_id'])
                ->firstOrFail()
                ->user_id,
            'storage_location_id' => function (array $attributes): int {
                return StorageLocation::factory()->create(['user_id' => $attributes['user_id']])->id;
            },
            'stored_at' => now(),
            'removed_at' => null,
        ];
    }
}
