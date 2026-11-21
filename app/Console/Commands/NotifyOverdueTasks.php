<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Notify assignees about tasks that passed their due date (run daily)';

    public function handle(): int
    {
        $tasks = Task::with(['assignees', 'project'])
            ->where('status', '!=', TaskStatus::Completed)
            ->whereDate('due_date', '<', today())
            ->get();

        foreach ($tasks as $task) {
            Notification::send($task->assignees, new TaskOverdue($task));
        }

        $this->info("Notified about {$tasks->count()} overdue task(s).");

        return self::SUCCESS;
    }
}
