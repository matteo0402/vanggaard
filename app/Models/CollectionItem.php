<?php

namespace App\Models;

use Database\Factories\CollectionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property int $release_id
 * @property bool $is_active
 * @property-read Release $release
 */
#[Fillable(['user_id', 'release_id', 'is_active'])]
class CollectionItem extends Model
{
    /** @use HasFactory<CollectionItemFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return HasOne<DiscogsCollectionInstance, $this> */
    public function discogsCollectionInstance(): HasOne
    {
        return $this->hasOne(DiscogsCollectionInstance::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withPivotValue('user_id', $this->user_id)
            ->withTimestamps();
    }

    /** @return BelongsToMany<Riddim, $this> */
    public function riddims(): BelongsToMany
    {
        return $this->belongsToMany(Riddim::class)
            ->withPivotValue('user_id', $this->user_id)
            ->withTimestamps();
    }

    /** @return HasMany<CollectionItemStorageAssignment, $this> */
    public function storageAssignments(): HasMany
    {
        return $this->hasMany(CollectionItemStorageAssignment::class);
    }

    /** @param Builder<CollectionItem> $query */
    public function scopeDisplayableFor(Builder $query, User $user): void
    {
        $query
            ->whereBelongsTo($user)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('discogsCollectionInstance')
                    ->orWhereHas(
                        'discogsCollectionInstance',
                        fn (Builder $query): Builder => $query->where(
                            'fetched_at',
                            '>',
                            now()->subHours(Release::DISPLAY_MAX_AGE_HOURS),
                        ),
                    );
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
