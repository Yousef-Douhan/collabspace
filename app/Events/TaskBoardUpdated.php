<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task on a project's Kanban board was created, changed, moved or deleted.
 * Open boards move the card in place ("moved") or offer a refresh (anything else).
 */
class TaskBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const MOVED = 'moved';

    public const DELETED = 'deleted';

    public int $projectId;

    public int $taskId;

    public string $status;

    public int $position;

    public function __construct(Task $task, public string $action, public ?string $actorName = null)
    {
        $this->projectId = $task->project_id;
        $this->taskId = $task->id;
        $this->status = $task->status->value;
        $this->position = (int) $task->position;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("project.{$this->projectId}");
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'action' => $this->action,
            'status' => $this->status,
            'position' => $this->position,
            'actor' => $this->actorName,
        ];
    }
}
