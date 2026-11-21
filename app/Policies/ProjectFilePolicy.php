<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function view(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin() || $file->project->hasMember($user);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->hasMember($user);
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $file->uploaded_by === $user->id || $file->project->isManagedBy($user);
    }
}
