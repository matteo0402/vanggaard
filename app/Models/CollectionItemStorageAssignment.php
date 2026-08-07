<?php

namespace App\Models;

use Database\Factories\CollectionItemStorageAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['collection_item_id', 'storage_location_id', 'user_id', 'stored_at', 'removed_at'])]
class CollectionItemStorageAssignment extends Model
{
    /** @use HasFactory<CollectionItemStorageAssignmentFactory> */
    use HasFactory;

    /** @return BelongsTo<CollectionItem, $this> */
    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StorageLocation, $this> */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stored_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }
}
