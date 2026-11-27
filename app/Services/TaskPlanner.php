<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AI task planning:
 *  - fromMeeting(): turns a meeting's summary / key points / transcript into concrete tasks
 *    (action items with an owner and a date when the meeting mentioned them);
 *  - forProject(): breaks a project description down into a starter plan across the tracks.
 *
 * Suggestions are only proposals: the user reviews, edits and picks them, then createMany()
 * creates the chosen ones through TaskService, so assignment notifications and the
 * Kanban ordering work exactly as for hand-made tasks.
 */
class TaskPlanner
{
    public function __construct(private AiService $ai, private TaskService $tasks, private ActivityLogger $activity) {}

    /** @return list<array{title:string, description:?string, track:string, priority:string, assignee_id:?int, due_date:?string}> */
    public function fromMeeting(User $user, Meeting $meeting): array
    {
        $project = $meeting->project ?? throw new RuntimeException(__('Link this meeting to a project first, so the tasks have a board to go to.'));

        $source = collect([
            $meeting->ai_summary ? "Summary:\n{$meeting->ai_summary}" : null,
            $meeting->key_points ? "Key points:\n- ".implode("\n- ", (array) $meeting->key_points) : null,
            $meeting->notes ? "Notes:\n{$meeting->notes}" : null,
            $meeting->transcript ? "Transcript:\n".mb_substr($meeting->transcript, 0, 12000) : null,
        ])->filter()->implode("\n\n");

        if ($source === '') {
            throw new RuntimeException(__('This meeting has no summary, notes or transcript to plan from yet.'));
        }

        return $this->suggest(
            $project,
            'Extract the concrete action items agreed in this meeting as tasks. Only include work that was actually '
                .'decided or assigned; use the person and date mentioned for each item when there is one.',
            "Meeting: {$meeting->title} ({$meeting->starts_at->toDateString()})\n\n{$source}",
            max: 8,
            today: $meeting->starts_at,
        );
    }

    /** @return list<array{title:string, description:?string, track:string, priority:string, assignee_id:?int, due_date:?string}> */
    public function forProject(User $user, Project $project): array
    {
        if (blank($project->description) && blank($project->wiki)) {
            throw new RuntimeException(__('Add a project description or wiki first, so the AI knows what to plan.'));
        }

        $existing = $project->tasks()->pluck('title')->take(40)->implode('; ');

        return $this->suggest(
            $project,
            'Break this project down into a starter plan of 6-10 tasks spread across the UI/UX, front-end, back-end and '
                .'graphic-design tracks, ordered roughly in the sequence they should happen. Skip work that already exists.',
            "Project: {$project->name}\nType: {$project->type}\n"
                .'Dates: '.($project->start_date?->toDateString() ?? '?').' → '.($project->end_date?->toDateString() ?? '?')."\n"
                ."Description:\n".($project->description ?: '-')."\n\nWiki:\n".mb_substr((string) $project->wiki, 0, 6000)
                ."\n\nExisting tasks: ".($existing ?: 'none'),
            max: 10,
            today: now(),
        );
    }

    /**
     * Creates the tasks the user kept (after editing). Every row is re-validated against the project.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(User $user, Project $project, array $rows, ?Meeting $meeting = null): Collection
    {
        $members = $project->members()->pluck('users.id')->push($project->owner_id)->unique();

        $created = DB::transaction(fn () => collect($rows)->map(fn (array $row) => $this->tasks->create($user, $project, [
            'title' => $row['title'],
            'description' => $row['description'] ?? null,
            'track' => $row['track'],
            'priority' => $row['priority'],
            'status' => TaskStatus::Todo->value,
            'due_date' => $row['due_date'] ?? null,
            'assignees' => isset($row['assignee_id']) && $members->contains((int) $row['assignee_id']) ? [(int) $row['assignee_id']] : [],
        ])));

        if ($meeting) {
            $this->activity->log($user, 'meeting.tasks_created', "created {$created->count()} tasks from \"{$meeting->title}\"", $meeting, $project);
        }

        return $created;
    }

    private function suggest(Project $project, string $instruction, string $input, int $max, Carbon|\DateTimeInterface $today): array
    {
        $members = $project->members()->get(['users.id', 'users.name', 'users.track'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'track' => $u->track?->value]);

        $result = $this->ai->chatJson(
            system: <<<PROMPT
            You plan work for a project-management app (Kanban). {$instruction}
            Return ONLY a JSON object: {"tasks": [ ... ]} with at most {$max} items. Each item:
              "title":       short imperative title (max 80 chars),
              "description": one or two sentences,
              "track":       one of "ui_ux", "front_end", "back_end", "graphic_design",
              "priority":    one of "high", "medium", "low",
              "assignee_id": the id of the best-fitting team member from the list (match the name mentioned, else by track), or null,
              "due_in_days": whole days from today until it should be done, or null.
            Write titles and descriptions in the same language as the input.
            PROMPT,
            user: 'Team members: '.json_encode($members, JSON_UNESCAPED_UNICODE)."\n\n".$input,
            maxTokens: 3000,
        );

        $memberIds = $members->pluck('id');
        $base = Carbon::parse($today)->startOfDay();

        $tasks = collect($result['tasks'] ?? [])
            ->filter(fn ($t) => is_array($t) && filled($t['title'] ?? null))
            ->take($max)
            ->map(function (array $t) use ($memberIds, $members, $base) {
                $assignee = isset($t['assignee_id']) && $memberIds->contains((int) $t['assignee_id']) ? (int) $t['assignee_id'] : null;
                $assigneeTrack = $assignee ? ($members->firstWhere('id', $assignee)['track'] ?? null) : null;
                $track = Track::tryFrom((string) ($t['track'] ?? ''))
                    ?? Track::tryFrom((string) $assigneeTrack)
                    ?? Track::BackEnd;
                $days = is_numeric($t['due_in_days'] ?? null) ? max(0, min(180, (int) $t['due_in_days'])) : null;

                return [
                    'title' => mb_substr(trim((string) $t['title']), 0, 255),
                    'description' => filled($t['description'] ?? null) ? mb_substr(trim((string) $t['description']), 0, 2000) : null,
                    'track' => $track->value,
                    'priority' => (Priority::tryFrom((string) ($t['priority'] ?? '')) ?? Priority::Medium)->value,
                    'assignee_id' => $assignee,
                    'due_date' => $days !== null ? $base->copy()->addDays($days)->toDateString() : null,
                ];
            })
            ->values()
            ->all();

        if ($tasks === []) {
            throw new RuntimeException(__('The AI did not suggest any tasks. Try again or add more detail.'));
        }

        return $tasks;
    }
}
