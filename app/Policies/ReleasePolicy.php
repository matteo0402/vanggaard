<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\User;

class ReleasePolicy
{
    public function view(User $user, Release $release): bool
    {
        return $this->ownsActiveCopy($user, $release);
    }

    public function refresh(User $user, Release $release): bool
    {
        return $this->ownsActiveCopy($user, $release);
    }

    public function updatePersonalMetadata(User $user, Release $release): bool
    {
        return $this->ownsActiveCopy($user, $release);
    }

    public function updateVocabulary(User $user, Release $release): bool
    {
        return $this->ownsActiveCopy($user, $release);
    }

    private function ownsActiveCopy(User $user, Release $release): bool
    {
        return $release->collectionItems()
            ->displayableFor($user)
            ->exists();
    }
}
