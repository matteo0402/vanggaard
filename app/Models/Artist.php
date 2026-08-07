<?php

namespace App\Models;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['discogs_id', 'name', 'real_name', 'profile', 'source_url', 'fetched_at'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    /** @return BelongsToMany<Release, $this, ArtistRelease, 'pivot'> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)
            ->using(ArtistRelease::class)
            ->withPivot(['id', 'position', 'credited_name', 'join_text', 'retired_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Credit, $this> */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
