<?php

namespace App\Services;

use App\Enums\AiRole;
use App\Enums\TaskStatus;
use App\Models\AiMessage;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Ask AI": answers questions about the workspace, grounded ONLY in data
 * the asking user is allowed to see, and keeps a per-user chat history.
 */
class AiAssistant
{
    private const HISTORY_TURNS = 10;

    public function __construct(private AiService $ai) {}

    public function ask(User $user, string $question): AiMessage
    {
        $history = $user->aiMessages()->latest('id')->limit(self::HISTORY_TURNS)->get()->reverse()
            ->map(fn (AiMessage $m) => ['role' => $m->role->value, 'content' => $m->content])
            ->values()
            ->all();

        $answer = $this->ai->chat(
            system: $this->systemPrompt(),
            user: 'Today is '.now()->toDateString().".\n\nWORKSPACE DATA (JSON):\n"
                .json_encode($this->context($user), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ."\n\nQUESTION:\n".$question,
            history: $history,
            maxTokens: 800,
        );

        // Only store the exchange once the AI actually answered.
        $user->aiMessages()->create(['role' => AiRole::User, 'content' => $question]);

        return $user->aiMessages()->create(['role' => AiRole::Assistant, 'content' => $answer]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are the CollabSpace assistant. Answer questions about the user's projects, tasks,
        meetings and teammates using ONLY the workspace data provided with each question.
        If the data does not contain the answer, say so plainly — never invent tasks, dates or people.
        Be concise (at most ~8 short lines or bullets) and use Markdown for lists.
        Reply in the same language as the question (Arabic or English).
        PROMPT;
    }

    /** Snapshot limited to what $user may see (same scopes as the API). */
    public function context(User $user): array
    {
        $projects = Project::visibleTo($user)
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
            ])
            ->latest()
            ->limit(20)
            ->get();

        return [
            'me' => ['name' => $user->name, 'job_title' => $user->job_title, 'track' => $user->track?->value],
            'projects' => $projects->map(fn (Project $p) => [
                'name' => $p->name,
                'status' => $p->status->value,
                'priority' => $p->priority->value,
                'end_date' => $p->end_date?->toDateString(),
                'tasks_total' => $p->tasks_count,
                'tasks_completed' => $p->completed_tasks_count,
                'completion' => $p->completion_percentage.'%',
            ]),
            'open_tasks' => Task::visibleTo($user)
                ->with(['project:id,name', 'assignees:id,name'])
                ->where('status', '!=', TaskStatus::Completed)
                ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_date')
                ->limit(40)
                ->get()
                ->map(fn (Task $t) => [
                    'title' => $t->title,
                    'project' => $t->project->name,
                    'status' => $t->status->value,
                    'priority' => $t->priority->value,
                    'progress' => $t->progress.'%',
                    'due' => $t->due_date?->toDateString(),
                    'overdue' => $t->isOverdue(),
                    'assignees' => $t->assignees->pluck('name')->all(),
                ]),
            'upcoming_meetings' => Meeting::visibleTo($user)
                ->with('project:id,name')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(10)
                ->get()
                ->map(fn (Meeting $m) => [
                    'title' => $m->title,
                    'project' => $m->project?->name,
                    'starts_at' => $m->starts_at->toDateTimeString(),
                    'location' => $m->location,
                ]),
            'teammates' => User::whereHas('projects', fn (Builder $q) => $q->whereIn('projects.id', $projects->pluck('id')))
                ->withCount(['tasks as open_tasks_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Completed)])
                ->get()
                ->map(fn (User $u) => [
                    'name' => $u->name,
                    'job_title' => $u->job_title,
                    'track' => $u->track?->value,
                    'availability' => $u->availability?->value,
                    'open_tasks' => $u->open_tasks_count,
                ]),
        ];
    }
}
