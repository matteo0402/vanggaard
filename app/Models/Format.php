<?php

namespace App\Models;

use Database\Factories\FormatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['release_id', 'position', 'name', 'quantity', 'text', 'descriptions', 'retired_at'])]
class Format extends Model
{
    /** @use HasFactory<FormatFactory> */
    use HasFactory;

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'descriptions' => 'array',
            'retired_at' => 'datetime',
        ];
    }
}
