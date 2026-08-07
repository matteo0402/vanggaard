<?php

namespace App\Models;

use Database\Factories\DiscogsAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property string $username
 * @property string $personal_access_token
 * @property string $source_url
 */
#[Fillable(['user_id', 'username', 'personal_access_token', 'source_url', 'fetched_at'])]
#[Hidden(['personal_access_token'])]
class DiscogsAccount extends Model
{
    /** @use HasFactory<DiscogsAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DiscogsSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(DiscogsSyncRun::class);
    }

    /** @return HasMany<DiscogsCollectionInstance, $this> */
    public function collectionInstances(): HasMany
    {
        return $this->hasMany(DiscogsCollectionInstance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'personal_access_token' => 'encrypted',
            'fetched_at' => 'datetime',
        ];
    }
}
