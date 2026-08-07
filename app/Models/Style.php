<?php

namespace App\Models;

use Database\Factories\StyleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Style extends Model
{
    /** @use HasFactory<StyleFactory> */
    use HasFactory;

    /** @return BelongsToMany<Release, $this, ReleaseStyle, 'pivot'> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)
            ->using(ReleaseStyle::class)
            ->withPivot(['id', 'position', 'retired_at'])
            ->withTimestamps();
    }
}
