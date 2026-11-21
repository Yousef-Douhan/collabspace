<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TaskService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $data  validated input (may contain "assignees")
     */
    public function create(User $creator, Project $project, array $data): Task
    {
        return DB::transaction(function () use ($creator, $project, $data) {
            $status = TaskStatus::from($data['status'] ?? TaskStatus::Todo->value);

            $task = new Task(Arr::except($data, ['assignees', 'project_id']));
            $task->project()->associate($project);
            $task->created_by = $creator->id;
            $task->status = $status;
            $task->position = $this->nextPosition($project, $status);
            $this->applyStatusSideEffects($task, $status, isset($data['progress']));
            $task->save();

            if (! empty($data['assignees'])) {
                $attached = $task->assignees()->sync($data['assignees'])['attached'];
                $this->notifyAssigned($creator, $task, $attached);
            }

            $this->activity->log($creator, 'task.created', "created task \"{$task->title}\"", $task, $project);

            return $task;
        });
    }

    public function update(User $actor, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($actor, $task, $data) {
            $newStatus = isset($data['status']) ? TaskStatus::from($data['status']) : null;

            // A status change through a normal update behaves like a move to the end of that column.
            if ($newStatus && $newStatus !== $task->status) {
                $this->move($actor, $task, $newStatus, null, log: false);
            }

            $task->fill(Arr::except($data, ['assignees', 'status', 'project_id']));
            $task->save();

            if (array_key_exists('assignees', $data)) {
                $attached = $task->assignees()->sync($data['assignees'] ?? [])['attached'];
                $this->notifyAssigned($actor, $task, $attached);
            }

            $this->activity->log($actor, 'task.updated', "updated task \"{$task->title}\"", $task, $task->project);

            return $task;
        });
    }

    /**
     * Kanban drag & drop: put the task in $status at $position (0-based; null = end of column)
     * and close the gaps so positions in both columns stay 0..n-1.
     */
    public function move(User $actor, Task $task, TaskStatus $status, ?int $position, bool $log = true): Task
    {
        return DB::transaction(function () use ($actor, $task, $status, $position, $log) {
            $oldStatus = $task->status;
            $column = fn (TaskStatus $s) => Task::where('project_id', $task->project_id)
                ->where('status', $s)
                ->whereKeyNot($task->id);

            // 1) Close the gap in the old column.
            $column($oldStatus)->where('position', '>', $task->position)->decrement('position');

            // 2) Clamp the target position and open a gap in the new column.
            $count = $column($status)->count();
            $position = $position === null ? $count : max(0, min($position, $count));
            $column($status)->where('position', '>=', $position)->increment('position');

            // 3) Place the task.
            $task->status = $status;
            $task->position = $position;
            if ($status !== $oldStatus) {
                $this->applyStatusSideEffects($task, $status);
            }
            $task->save();

            if ($log && $status !== $oldStatus) {
                $this->activity->log($actor, 'task.moved', "moved \"{$task->title}\" to {$status->label()}", $task, $task->project);
            }

            return $task;
        });
    }

    public function delete(User $actor, Task $task): void
    {
        DB::transaction(function () use ($task) {
            Task::where('project_id', $task->project_id)
                ->where('status', $task->status)
                ->where('position', '>', $task->position)
                ->decrement('position');

            $task->delete();
        });

        $this->activity->log($actor, 'task.deleted', "deleted task \"{$task->title}\"", $task, $task->project);
    }

    /** Notify people who were just assigned (never the person doing the assigning). */
    private function notifyAssigned(User $by, Task $task, array $userIds): void
    {
        $recipients = User::whereIn('id', $userIds)->whereKeyNot($by->id)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskAssigned($task->loadMissing('project'), $by));
        }
    }

    private function nextPosition(Project $project, TaskStatus $status): int
    {
        return Task::where('project_id', $project->id)->where('status', $status)->count();
    }

    /** Keep progress / completed_at consistent with the status. */
    private function applyStatusSideEffects(Task $task, TaskStatus $status, bool $progressGiven = false): void
    {
        if ($status === TaskStatus::Completed) {
            $task->progress = 100;
            $task->completed_at = now();

            return;
        }

        $task->completed_at = null;

        if (! $progressGiven) {
            $task->progress = match ($status) {
                TaskStatus::Todo => 0,
                TaskStatus::InProgress => max((int) $task->progress, 10),
                TaskStatus::InReview => max((int) $task->progress, 80),
                default => (int) $task->progress,
            };
        }
    }
}
