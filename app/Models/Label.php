<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['discogs_id', 'name', 'profile', 'contact_info', 'source_url', 'fetched_at'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /** @return BelongsToMany<Release, $this, LabelRelease, 'pivot'> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)
            ->using(LabelRelease::class)
            ->withPivot(['id', 'position', 'catalog_number', 'retired_at'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
