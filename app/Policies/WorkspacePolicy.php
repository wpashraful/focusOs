<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /** Any workspace member can view */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->users()->where('user_id', $user->id)->exists();
    }

    /** Only owner can update */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /** Only owner can delete */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /** Alias used by controller: $this->authorize('owner', $workspace) */
    public function owner(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
