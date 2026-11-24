<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

/**
 * Everyone can browse teams (it is the workspace directory).
 * Admins and managers create teams; a team's leads (or an admin) run it.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->isAdmin() || $team->isLedBy($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->isAdmin() || $team->isLedBy($user);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
