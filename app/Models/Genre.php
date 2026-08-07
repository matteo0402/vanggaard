<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    /** @return BelongsToMany<Release, $this, GenreRelease, 'pivot'> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)
            ->using(GenreRelease::class)
            ->withPivot(['id', 'position', 'retired_at'])
            ->withTimestamps();
    }
}
