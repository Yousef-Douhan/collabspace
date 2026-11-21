<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use App\Support\Trend;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Everything the Dashboard screen shows, in one call. */
class DashboardService
{
    public function build(User $user, ?int $projectId = null, string $period = 'month'): array
    {
        $since = $this->periodStart($period);
        $scopeProject = fn (Builder $q) => $projectId ? $q->where('project_id', $projectId) : $q;

        $tasks = Task::visibleTo($user)->tap($scopeProject);
        $periodTasks = (clone $tasks)->where('created_at', '>=', $since);

        $counts = (clone $periodTasks)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $completed = (int) ($counts[TaskStatus::Completed->value] ?? 0);

        $projects = Project::visibleTo($user)
            ->when($projectId, fn (Builder $q) => $q->whereKey($projectId))
            ->where('status', 'active')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
            ])
            ->latest()
            ->limit(5)
            ->get();

        $projectIds = Project::visibleTo($user)->pluck('id');

        return [
            'period' => $period,
            'stats' => [
                'todo' => (int) ($counts[TaskStatus::Todo->value] ?? 0),
                'in_progress' => (int) ($counts[TaskStatus::InProgress->value] ?? 0),
                'in_review' => (int) ($counts[TaskStatus::InReview->value] ?? 0),
                'completed' => $completed,
                'total' => $total,
                'completion_rate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'overdue' => (clone $tasks)->where('status', '!=', TaskStatus::Completed)->whereDate('due_date', '<', today())->count(),
            ],
            'active_projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'priority' => $p->priority->value,
                'completion_percentage' => $p->completion_percentage,
                'end_date' => $p->end_date?->toDateString(),
            ]),
            'priority_tasks' => (clone $tasks)
                ->with(['project:id,name', 'assignees:id,name'])
                ->where('status', '!=', TaskStatus::Completed)
                ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_date')
                ->limit(6)
                ->get()
                ->map(fn (Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'project' => $t->project->name,
                    'priority' => $t->priority->value,
                    'status' => $t->status->value,
                    'progress' => $t->progress,
                    'due_date' => $t->due_date?->toDateString(),
                    'is_overdue' => $t->isOverdue(),
                ]),
            'team_status' => User::whereHas('projects', fn (Builder $q) => $q->whereIn('projects.id', $projectIds))
                ->withCount([
                    'tasks',
                    'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
                ])
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'track' => $u->track?->value,
                    'availability' => $u->availability?->value,
                    'tasks' => $u->tasks_count,
                    'completed_tasks' => $u->completed_tasks_count,
                ]),
            'upcoming_meetings' => Meeting::visibleTo($user)
                ->with('project:id,name')
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(4)
                ->get()
                ->map(fn (Meeting $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'project' => $m->project?->name,
                    'starts_at' => $m->starts_at->toIso8601String(),
                ]),
            'recent_files' => ProjectFile::visibleTo($user)
                ->with('project:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProjectFile $f) => [
                    'id' => $f->id,
                    'name' => $f->original_name,
                    'size' => $f->formatted_size,
                    'project' => $f->project->name,
                ]),
            'recent_activity' => ActivityLog::with('user:id,name')
                ->whereIn('project_id', $projectIds)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (ActivityLog $a) => [
                    'user' => $a->user?->name,
                    'action' => $a->action,
                    'description' => $a->description,
                    'created_at' => $a->created_at->toIso8601String(),
                ]),
            'changes' => $this->monthOverMonth(clone $tasks),
            'charts' => [
                'project_overview' => $this->monthlySeries(clone $tasks, 6),
            ],
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * "+9% from last month" for each stat card: tasks created this month vs last month,
     * and the completion-rate difference in percentage points.
     */
    private function monthOverMonth(Builder $tasks): array
    {
        $thisMonth = CarbonImmutable::now()->startOfMonth();
        $lastMonth = $thisMonth->subMonth();

        $count = function (CarbonImmutable $from, CarbonImmutable $to) use ($tasks) {
            return (clone $tasks)->whereBetween('created_at', [$from, $to])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        };

        $now = $count($thisMonth, CarbonImmutable::now());
        $before = $count($lastMonth, $thisMonth->subSecond());

        $pick = fn ($counts, TaskStatus $s) => (int) ($counts[$s->value] ?? 0);
        $rate = fn ($counts) => Trend::rate($pick($counts, TaskStatus::Completed), (int) $counts->sum());

        return [
            'todo' => Trend::change($pick($now, TaskStatus::Todo), $pick($before, TaskStatus::Todo)),
            'in_progress' => Trend::change($pick($now, TaskStatus::InProgress), $pick($before, TaskStatus::InProgress)),
            'completed' => Trend::change($pick($now, TaskStatus::Completed), $pick($before, TaskStatus::Completed)),
            'completion_rate_points' => $rate($now) - $rate($before),
        ];
    }

    /** Tasks created vs completed per month — the "Project overview" line chart. */
    private function monthlySeries(Builder $tasks, int $months): array
    {
        $since = Trend::months($months)->first();
        $rows = (clone $tasks)
            ->where(fn (Builder $q) => $q->where('created_at', '>=', $since)->orWhere('completed_at', '>=', $since))
            ->get(['id', 'created_at', 'completed_at']);

        return Trend::months($months)->map(fn (CarbonImmutable $month) => [
            'month' => $month->format('M'),
            'created' => $rows->filter(fn (Task $t) => $t->created_at->isSameMonth($month))->count(),
            'completed' => $rows->filter(fn (Task $t) => $t->completed_at?->isSameMonth($month))->count(),
        ])->values()->all();
    }

    private function periodStart(string $period): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => $now->startOfDay(),
            'week' => $now->startOfWeek(),
            'quarter' => $now->subMonths(3)->startOfDay(),
            'year' => $now->startOfYear(),
            'all' => CarbonImmutable::create(2000),
            default => $now->startOfMonth(),
        };
    }
}
