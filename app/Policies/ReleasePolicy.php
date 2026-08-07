<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\User;

class ReleasePolicy
{
    public function refresh(User $user, Release $release): bool
    {
        return $release->collectionItems()
            ->whereBelongsTo($user)
            ->where('is_active', true)
            ->exists();
    }
}
