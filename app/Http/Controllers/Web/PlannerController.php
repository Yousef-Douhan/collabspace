<?php

namespace App\Http\Controllers\Web;

use App\Enums\Priority;
use App\Enums\Track;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * AI task planning (JSON). Used by the Blade pages through fetch and exposed under /api/v1 too.
 *   POST meetings/{meeting}/plan        → suggested tasks from the meeting's action items
 *   POST projects/{project}/plan        → a starter plan from the project description
 *   POST projects/{project}/tasks/bulk  → create the tasks the user kept
 */
class PlannerController extends Controller
{
    public function __construct(private TaskPlanner $planner) {}

    public function fromMeeting(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('view', $meeting);
        if ($meeting->project) {
            Gate::authorize('create', [Task::class, $meeting->project]);
        }

        return $this->respond(fn () => $this->planner->fromMeeting($request->user(), $meeting->load('project')));
    }

    public function forProject(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);

        return $this->respond(fn () => $this->planner->forProject($request->user(), $project));
    }

    public function createMany(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);

        $data = $request->validate([
            'meeting_id' => ['nullable', 'integer', Rule::exists('meetings', 'id')->where('project_id', $project->id)],
            'tasks' => ['required', 'array', 'min:1', 'max:20'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.track' => ['required', Rule::enum(Track::class)],
            'tasks.*.priority' => ['required', Rule::enum(Priority::class)],
            'tasks.*.assignee_id' => ['nullable', 'integer'],
            'tasks.*.due_date' => ['nullable', 'date'],
        ]);

        $meeting = isset($data['meeting_id']) ? Meeting::find($data['meeting_id']) : null;
        $created = $this->planner->createMany($request->user(), $project, $data['tasks'], $meeting);

        return response()->json([
            'message' => trans_choice(':count task created.|:count tasks created.', $created->count()),
            'data' => ['created' => $created->count(), 'ids' => $created->pluck('id')],
            'redirect' => route('tasks.index', ['project_id' => $project->id]),
        ], 201);
    }

    private function respond(callable $suggest): JsonResponse
    {
        try {
            return response()->json(['data' => $suggest()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
