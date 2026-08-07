<?php

namespace App\Models;

use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['release_id', 'sequence', 'position', 'title', 'duration', 'type', 'retired_at'])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }
}
