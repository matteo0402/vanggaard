<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'release_riddim', incrementing: false)]
#[Fillable(['user_id', 'release_id', 'riddim_id'])]
class ReleaseRiddim extends Model
{
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

    /** @return BelongsTo<Riddim, $this> */
    public function riddim(): BelongsTo
    {
        return $this->belongsTo(Riddim::class);
    }
}
