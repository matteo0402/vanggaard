<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DiscogsSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 * @property int $items_seen
 * @property int $items_created
 * @property int $items_updated
 * @property int $items_removed
 * @property int $last_completed_page
 * @property int|null $total_pages
 * @property int|null $total_items
 * @property CarbonInterface|null $completed_at
 * @property string|null $error_message
 */
#[Fillable(['discogs_account_id', 'kind', 'status', 'is_full_reconciliation', 'items_seen', 'items_created', 'items_updated', 'items_removed', 'last_completed_page', 'total_pages', 'total_items', 'started_at', 'completed_at', 'error_message'])]
class DiscogsSyncRun extends Model
{
    /** @use HasFactory<DiscogsSyncRunFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'is_full_reconciliation' => false,
        'items_seen' => 0,
        'items_created' => 0,
        'items_updated' => 0,
        'items_removed' => 0,
        'last_completed_page' => 0,
    ];

    /** @return BelongsTo<DiscogsAccount, $this> */
    public function discogsAccount(): BelongsTo
    {
        return $this->belongsTo(DiscogsAccount::class);
    }

    /** @return HasMany<DiscogsCollectionInstance, $this> */
    public function seenCollectionInstances(): HasMany
    {
        return $this->hasMany(DiscogsCollectionInstance::class, 'last_seen_sync_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_full_reconciliation' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
