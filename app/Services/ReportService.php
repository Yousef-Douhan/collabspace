<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    /** Live numbers for the Report screen. */
    public function overview(User $user, ?int $projectId = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $projects = Project::visibleTo($user)
            ->when($projectId, fn (Builder $q) => $q->whereKey($projectId))
            ->get();
        $projectIds = $projects->pluck('id');

        $tasks = Task::whereIn('project_id', $projectIds)
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->get(['id', 'project_id', 'status', 'due_date']);

        $isOverdue = fn (Task $t) => $t->status !== TaskStatus::Completed && $t->due_date && $t->due_date->lt(today());

        $members = User::whereHas('projects', fn (Builder $q) => $q->whereIn('projects.id', $projectIds))
            ->with(['tasks' => fn ($q) => $q->whereIn('project_id', $projectIds)])
            ->orderBy('name')
            ->get();

        return [
            'period' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'totals' => [
                'projects' => $projects->count(),
                'tasks' => $tasks->count(),
                'completed_tasks' => $tasks->where('status', TaskStatus::Completed)->count(),
                'overdue_tasks' => $tasks->filter($isOverdue)->count(),
                'completion_rate' => $this->rate($tasks->where('status', TaskStatus::Completed)->count(), $tasks->count()),
                'meetings' => Meeting::whereIn('project_id', $projectIds)
                    ->when($from, fn (Builder $q) => $q->where('starts_at', '>=', $from))
                    ->when($to, fn (Builder $q) => $q->where('starts_at', '<=', $to))
                    ->count(),
            ],
            'project_progress' => $projects->map(function (Project $p) use ($tasks, $isOverdue) {
                $own = $tasks->where('project_id', $p->id);
                $done = $own->where('status', TaskStatus::Completed)->count();

                return [
                    'project_id' => $p->id,
                    'project' => $p->name,
                    'status' => $p->status->value,
                    'tasks' => $own->count(),
                    'completed' => $done,
                    'overdue' => $own->filter($isOverdue)->count(),
                    'completion_rate' => $this->rate($done, $own->count()),
                ];
            })->values(),
            'team_performance' => $members->map(function (User $u) use ($isOverdue) {
                $done = $u->tasks->where('status', TaskStatus::Completed)->count();

                return [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'track' => $u->track?->value,
                    'assigned' => $u->tasks->count(),
                    'completed' => $done,
                    'overdue' => $u->tasks->filter($isOverdue)->count(),
                    'completion_rate' => $this->rate($done, $u->tasks->count()),
                ];
            })->values(),
        ];
    }

    /** Saves a snapshot of the numbers so the report never changes afterwards. */
    public function generate(User $user, ReportType $type, ?int $projectId, ?CarbonInterface $from, ?CarbonInterface $to, ?string $notes): Report
    {
        $overview = $this->overview($user, $projectId, $from, $to);

        $data = match ($type) {
            ReportType::ProjectProgress => ['totals' => $overview['totals'], 'rows' => $overview['project_progress']],
            ReportType::TeamPerformance => ['totals' => $overview['totals'], 'rows' => $overview['team_performance']],
            ReportType::Tasks => ['totals' => $overview['totals'], 'rows' => $overview['project_progress']],
            ReportType::Meetings => ['totals' => ['meetings' => $overview['totals']['meetings']], 'rows' => []],
        };

        return Report::create([
            'created_by' => $user->id,
            'project_id' => $projectId,
            'type' => $type,
            'period_start' => $from,
            'period_end' => $to,
            'notes' => $notes,
            'data' => $data,
        ]);
    }

    private function rate(int $done, int $total): int
    {
        return $total > 0 ? (int) round($done / $total * 100) : 0;
    }
}
