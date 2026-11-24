<?php

namespace App\Notifications;

use App\Models\Task;

class TaskOverdue extends WorkspaceNotification
{
    public function __construct(public Task $task) {}

    public function type(): string
    {
        return 'task_overdue';
    }

    public function title(): string
    {
        return 'Task overdue';
    }

    public function line(): array
    {
        return ['":task" was due on :date.', [
            'task' => $this->task->title,
            'date' => $this->task->due_date->toDateString(),
        ]];
    }

    public function context(): array
    {
        return ['task_id' => $this->task->id, 'project_id' => $this->task->project_id];
    }
}
