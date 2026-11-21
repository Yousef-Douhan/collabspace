<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->project->hasMember($user);
    }

    /** Any member of the project can create tasks in it. */
    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->hasMember($user);
    }

    /** Assignees, the creator, project managers and admins can edit / move a task. */
    public function update(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            || $task->project->isManagedBy($user)
            || $task->assignees()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $task->project->isManagedBy($user);
    }
}
