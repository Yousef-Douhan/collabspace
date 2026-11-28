<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Support\Trend;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Live numbers for the Report screen.
     *
     * @return array<string, mixed>
     */
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
            'kpis' => $this->kpis($projects, $members->pluck('id')),
            'charts' => $this->charts($projectIds, $members->pluck('id')),
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
                    'track' => $u->track->value,
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

    /**
     * The three cards at the top of the Report screen, each with its change vs last month.
     *
     * @param  EloquentCollection<int, Project>  $projects
     * @param  Collection<int, mixed>  $memberIds
     * @return array<string, mixed>
     */
    private function kpis(EloquentCollection $projects, Collection $memberIds): array
    {
        $thisMonth = CarbonImmutable::now()->startOfMonth();
        $lastMonth = $thisMonth->subMonth();
        $projectIds = $projects->pluck('id');

        $taskRate = function (CarbonImmutable $from, CarbonImmutable $to) use ($projectIds) {
            $tasks = Task::whereIn('project_id', $projectIds)->whereBetween('created_at', [$from, $to])->get(['status']);

            return Trend::rate($tasks->where('status', TaskStatus::Completed)->count(), $tasks->count());
        };

        // A member is "active" in a period if they did anything (activity log) in it.
        $activeRate = function (CarbonImmutable $from, CarbonImmutable $to) use ($memberIds, $projectIds) {
            $active = ActivityLog::whereIn('user_id', $memberIds)
                ->whereIn('project_id', $projectIds)
                ->whereBetween('created_at', [$from, $to])
                ->distinct()
                ->count('user_id');

            return Trend::rate($active, $memberIds->count());
        };

        $completedProjects = $projects->filter(fn (Project $p) => $p->status->value === 'completed' || $p->completion_percentage === 100)->count();

        $taskNow = $taskRate($thisMonth, CarbonImmutable::now());
        $taskBefore = $taskRate($lastMonth, $thisMonth->subSecond());
        $activeNow = $activeRate($thisMonth, CarbonImmutable::now());
        $activeBefore = $activeRate($lastMonth, $thisMonth->subSecond());

        return [
            'task_completion_rate' => ['value' => $taskNow, 'change_points' => $taskNow - $taskBefore],
            'project_completion_rate' => ['value' => Trend::rate($completedProjects, $projects->count()), 'change_points' => null],
            'team_activity_rate' => ['value' => $activeNow, 'change_points' => $activeNow - $activeBefore],
        ];
    }

    /**
     * Series for the three charts on the Report screen (last 6 months).
     *
     * @param  Collection<int, mixed>  $projectIds
     * @param  Collection<int, mixed>  $memberIds
     * @return array<string, mixed>
     */
    private function charts(Collection $projectIds, Collection $memberIds): array
    {
        $months = Trend::months(6);
        $tasks = Task::whereIn('project_id', $projectIds)->get(['id', 'status', 'due_date', 'created_at', 'completed_at']);
        $activity = ActivityLog::whereIn('user_id', $memberIds)
            ->where('created_at', '>=', $months->first())
            ->get(['id', 'created_at']);

        return [
            // "Task Completion Time vs Deadline": share of tasks completed that month on/before their due date.
            'completion_vs_deadline' => $months->map(function (CarbonImmutable $m) use ($tasks) {
                $done = $tasks->filter(fn (Task $t) => $t->completed_at?->isSameMonth($m));
                $onTime = $done->filter(fn (Task $t) => $t->due_date === null || $t->completed_at->lte($t->due_date->endOfDay()));

                return ['month' => $m->translatedFormat('M'), 'completed' => $done->count(), 'on_time' => $onTime->count(), 'on_time_rate' => Trend::rate($onTime->count(), $done->count())];
            })->values()->all(),

            // "Projects Progress": cumulative % of tasks completed by the end of each month.
            'projects_progress' => $months->map(function (CarbonImmutable $m) use ($tasks) {
                $end = $m->endOfMonth();
                $existing = $tasks->filter(fn (Task $t) => $t->created_at->lte($end));
                $done = $existing->filter(fn (Task $t) => $t->completed_at && $t->completed_at->lte($end));

                return ['month' => $m->translatedFormat('M'), 'progress' => Trend::rate($done->count(), $existing->count())];
            })->values()->all(),

            // "Worker Activity Rate": number of actions logged by the team per month…
            'worker_activity' => $months->map(fn (CarbonImmutable $m) => [
                'month' => $m->translatedFormat('M'),
                'actions' => $activity->filter(fn ($a) => $a->created_at->isSameMonth($m))->count(),
            ])->values()->all(),

            // …and per week for the last 8 weeks (Figma "Monthly ▾" menu on that chart).
            'worker_activity_weekly' => collect(range(7, 0))->map(function (int $back) use ($activity) {
                $from = CarbonImmutable::now()->startOfWeek()->subWeeks($back);
                $to = $from->addWeek();

                return [
                    'month' => $from->translatedFormat('M j'),
                    'actions' => $activity->filter(fn ($a) => $a->created_at >= $from && $a->created_at < $to)->count(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Drafts the "Add Note" text of a detailed report with the AI model, from the same
     * numbers the Report screen shows. The user can edit it before submitting.
     *
     * @throws \RuntimeException when the AI is not configured or fails
     */
    public function aiDraft(User $user, ReportType $type, ?int $projectId, ?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $o = $this->overview($user, $projectId, $from, $to);
        /** @var Collection<int, array<string, mixed>> $team */
        $team = $o['team_performance'];
        $facts = json_encode([
            'report_type' => $type->label(),
            'period' => $o['period'],
            'totals' => $o['totals'],
            'kpis' => $o['kpis'],
            'projects' => $o['project_progress'],
            'team' => $team->map(fn (array $m) => Arr::only($m, ['name', 'assigned', 'completed', 'overdue', 'completion_rate'])),
        ], JSON_UNESCAPED_UNICODE);

        $text = trim(app(AiService::class)->chat(
            system: 'You write short, factual status reports for a project-management app. '
                .'Use only the numbers given. 4-7 sentences or bullets: overall progress, risks (overdue work), '
                .'who is doing well or needs help, and 1-2 next steps. Plain text, no markdown headings. '
                .'Write in '.(['ar' => 'Arabic', 'fr' => 'French'][app()->getLocale()] ?? 'English').'.',
            user: $facts,
            maxTokens: 500,
        ));

        if ($text === '') {
            throw new \RuntimeException(__('The AI request failed.'));
        }

        return $text;
    }

    private function rate(int $done, int $total): int
    {
        return $total > 0 ? (int) round($done / $total * 100) : 0;
    }
}
