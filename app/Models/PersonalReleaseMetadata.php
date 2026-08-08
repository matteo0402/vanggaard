<?php

namespace App\Models;

use Database\Factories\PersonalReleaseMetadataFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $corrected_year
 * @property bool $is_year_approximate
 * @property int|null $rating
 * @property string|null $personal_notes
 * @property bool $is_favourite
 * @property bool $is_dj_ready
 * @property int|null $energy
 * @property float|null $bpm
 */
#[Fillable([
    'user_id',
    'release_id',
    'personal_notes',
    'rating',
    'corrected_year',
    'is_year_approximate',
    'is_favourite',
    'is_dj_ready',
    'energy',
    'bpm',
])]
class PersonalReleaseMetadata extends Model
{
    /** @use HasFactory<PersonalReleaseMetadataFactory> */
    use HasFactory;

    protected $attributes = [
        'is_year_approximate' => false,
        'is_favourite' => false,
        'is_dj_ready' => false,
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

    public function effectiveYear(): ?int
    {
        if ($this->corrected_year !== null) {
            return $this->corrected_year;
        }

        if ($this->release === null || ! $this->release->isFreshForDisplay()) {
            return null;
        }

        return $this->release->released_year;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_year_approximate' => 'boolean',
            'is_favourite' => 'boolean',
            'is_dj_ready' => 'boolean',
            'bpm' => 'float',
        ];
    }
}
