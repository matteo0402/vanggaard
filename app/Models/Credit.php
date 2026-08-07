<?php

namespace App\Models;

use Database\Factories\CreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['release_id', 'artist_id', 'position', 'role', 'credited_name', 'join_text', 'retired_at'])]
class Credit extends Model
{
    /** @use HasFactory<CreditFactory> */
    use HasFactory;

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }
}
