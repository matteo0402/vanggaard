<?php

namespace App\Models;

use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['release_id', 'position', 'uri', 'title', 'description', 'duration', 'embed', 'retired_at'])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'embed' => false,
    ];

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'embed' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }
}
