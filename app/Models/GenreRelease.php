<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(incrementing: true)]
class GenreRelease extends Pivot
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }
}
