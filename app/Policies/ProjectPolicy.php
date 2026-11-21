<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->id === $project->owner_id;
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    /** Any member can edit the wiki — it's shared documentation. */
    public function updateWiki(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}
