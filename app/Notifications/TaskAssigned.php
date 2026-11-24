<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;

class TaskAssigned extends WorkspaceNotification
{
    public function __construct(public Task $task, public User $by) {}

    public function type(): string
    {
        return 'task_assigned';
    }

    public function title(): string
    {
        return 'New task assigned';
    }

    public function line(): array
    {
        return [':name assigned you ":task" in :project.', [
            'name' => $this->by->name,
            'task' => $this->task->title,
            'project' => $this->task->project->name,
        ]];
    }

    public function context(): array
    {
        return ['task_id' => $this->task->id, 'project_id' => $this->task->project_id];
    }
}
