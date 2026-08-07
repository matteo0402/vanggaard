<?php

namespace App\Models;

use Database\Factories\PersonalReleaseMetadataFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $corrected_year
 * @property int|null $rating
 * @property string|null $personal_notes
 */
#[Fillable(['user_id', 'release_id', 'personal_notes', 'rating', 'corrected_year'])]
class PersonalReleaseMetadata extends Model
{
    /** @use HasFactory<PersonalReleaseMetadataFactory> */
    use HasFactory;

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
}
