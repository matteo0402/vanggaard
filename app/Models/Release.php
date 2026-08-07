<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $released_year
 * @property int $id
 * @property int $discogs_id
 * @property string $refresh_status
 * @property string|null $country
 * @property string|null $released
 * @property string|null $refresh_error
 * @property array<string, mixed> $raw_payload
 * @property string $source_url
 * @property string $title
 * @property CarbonInterface $fetched_at
 * @property CarbonInterface|null $refresh_attempted_at
 * @property CarbonInterface|null $refresh_failed_at
 */
#[Fillable(['discogs_id', 'master_discogs_id', 'title', 'country', 'released_year', 'released', 'notes', 'data_quality', 'source_url', 'fetched_at', 'refresh_status', 'refresh_attempted_at', 'refresh_failed_at', 'refresh_error', 'discogs_changed_at', 'source_hash', 'raw_payload', 'image_urls'])]
class Release extends Model
{
    public const DISPLAY_MAX_AGE_HOURS = 6;

    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    protected $attributes = [
        'refresh_status' => 'idle',
    ];

    /** @return HasMany<CollectionItem, $this> */
    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }

    /** @return HasMany<PersonalReleaseMetadata, $this> */
    public function personalMetadata(): HasMany
    {
        return $this->hasMany(PersonalReleaseMetadata::class);
    }

    /** @return BelongsToMany<Artist, $this, ArtistRelease, 'pivot'> */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)
            ->using(ArtistRelease::class)
            ->withPivot(['id', 'position', 'credited_name', 'join_text', 'retired_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Label, $this, LabelRelease, 'pivot'> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)
            ->using(LabelRelease::class)
            ->withPivot(['id', 'position', 'catalog_number', 'retired_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Credit, $this> */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /** @return HasMany<Track, $this> */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /** @return HasMany<Format, $this> */
    public function formats(): HasMany
    {
        return $this->hasMany(Format::class);
    }

    /** @return BelongsToMany<Genre, $this, GenreRelease, 'pivot'> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)
            ->using(GenreRelease::class)
            ->withPivot(['id', 'position', 'retired_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Style, $this, ReleaseStyle, 'pivot'> */
    public function styles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class)
            ->using(ReleaseStyle::class)
            ->withPivot(['id', 'position', 'retired_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Video, $this> */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function isFreshForDisplay(): bool
    {
        return $this->fetched_at->gt(now()->subHours(self::DISPLAY_MAX_AGE_HOURS));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'image_urls' => 'array',
            'raw_payload' => 'array',
            'fetched_at' => 'datetime',
            'refresh_attempted_at' => 'datetime',
            'refresh_failed_at' => 'datetime',
            'discogs_changed_at' => 'datetime',
        ];
    }
}
