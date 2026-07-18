<?php

namespace App\Policies;

use App\Models\FutureIdea;
use App\Models\User;

class FutureIdeaPolicy
{
    public function update(User $user, FutureIdea $idea): bool
    {
        return $user->id === $idea->user_id;
    }

    public function delete(User $user, FutureIdea $idea): bool
    {
        return $user->id === $idea->user_id;
    }
}
