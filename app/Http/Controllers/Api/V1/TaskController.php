<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\MoveTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    /**
     * GET /tasks?project_id=&status=&priority=&track=&assignee=me|{id}&overdue=1&q=
     * GET /tasks?view=board  → { todo: [...], in_progress: [...], in_review: [...], completed: [...] }
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        $query = Task::visibleTo($user)
            ->with(['project:id,name', 'assignees'])
            ->withCount('files')
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('track'), fn (Builder $q) => $q->where('track', $request->input('track')))
            ->when($request->filled('assignee'), function (Builder $q) use ($request, $user) {
                $id = $request->input('assignee') === 'me' ? $user->id : $request->integer('assignee');
                $q->whereHas('assignees', fn (Builder $a) => $a->whereKey($id));
            })
            ->when($request->boolean('overdue'), fn (Builder $q) => $q
                ->where('status', '!=', TaskStatus::Completed)
                ->whereDate('due_date', '<', today()))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->string('q').'%'));

        if ($request->input('view') === 'board') {
            $tasks = $query->orderBy('position')->get();

            return response()->json([
                'data' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [
                    $status->value => TaskResource::collection($tasks->where('status', $status)->values()),
                ]),
            ]);
        }

        return TaskResource::collection(
            $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_date')
                ->paginate(min(max($request->integer('per_page', 20), 1), 100))
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));
        $task = $this->tasks->create($request->user(), $project, $request->validated());

        return $this->resource($task)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return $this->resource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->tasks->update($request->user(), $task, $request->validated());

        return $this->resource($task);
    }

    /** PATCH /tasks/{task}/move  { status, position } — Kanban drag & drop */
    public function move(MoveTaskRequest $request, Task $task): TaskResource
    {
        $this->tasks->move(
            $request->user(),
            $task,
            TaskStatus::from($request->validated('status')),
            $request->validated('position'),
        );

        return $this->resource($task);
    }

    public function destroy(Request $request, Task $task): Response
    {
        Gate::authorize('delete', $task);

        $this->tasks->delete($request->user(), $task);

        return response()->noContent();
    }

    private function resource(Task $task): TaskResource
    {
        return new TaskResource($task->fresh()->load(['project:id,name', 'creator', 'assignees'])->loadCount('files'));
    }
}
