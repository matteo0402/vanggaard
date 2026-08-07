<?php

namespace Database\Factories;

use App\Models\DiscogsAccount;
use App\Models\DiscogsSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscogsSyncRun>
 */
class DiscogsSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discogs_account_id' => DiscogsAccount::factory(),
            'kind' => 'collection_reconciliation',
            'status' => 'pending',
            'is_full_reconciliation' => false,
            'items_seen' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_removed' => 0,
            'last_completed_page' => 0,
            'total_pages' => null,
            'total_items' => null,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ];
    }
}
