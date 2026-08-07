<?php

namespace App\Models;

use Database\Factories\StorageLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'parent_id', 'kind', 'name', 'position'])]
class StorageLocation extends Model
{
    /** @use HasFactory<StorageLocationFactory> */
    use HasFactory;

    protected $attributes = [
        'position' => 0,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StorageLocation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<StorageLocation, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CollectionItemStorageAssignment, $this> */
    public function collectionItemAssignments(): HasMany
    {
        return $this->hasMany(CollectionItemStorageAssignment::class);
    }
}
