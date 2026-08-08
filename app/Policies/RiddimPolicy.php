<?php

namespace App\Policies;

use App\Models\Riddim;
use App\Models\User;

class RiddimPolicy
{
    public function update(User $user, Riddim $riddim): bool
    {
        return $riddim->user_id === $user->id;
    }

    public function delete(User $user, Riddim $riddim): bool
    {
        return $riddim->user_id === $user->id;
    }
}
