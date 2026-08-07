<?php

namespace App\Models;

use Database\Factories\DiscogsCollectionInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['discogs_account_id', 'collection_item_id', 'user_id', 'last_seen_sync_run_id', 'discogs_instance_id', 'discogs_folder_id', 'source_url', 'fetched_at'])]
class DiscogsCollectionInstance extends Model
{
    /** @use HasFactory<DiscogsCollectionInstanceFactory> */
    use HasFactory;

    /** @return BelongsTo<DiscogsAccount, $this> */
    public function discogsAccount(): BelongsTo
    {
        return $this->belongsTo(DiscogsAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CollectionItem, $this> */
    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    /** @return BelongsTo<DiscogsSyncRun, $this> */
    public function lastSeenSyncRun(): BelongsTo
    {
        return $this->belongsTo(DiscogsSyncRun::class, 'last_seen_sync_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }
}
