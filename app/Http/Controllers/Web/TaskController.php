<?php

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\MoveTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityLogger;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    /** The Kanban board. Filters: project_id, mine=1, due=overdue|week, priority, q. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $tasks = Task::visibleTo($user)
            ->with(['project:id,name', 'assignees'])
            ->withCount(['comments', 'files'])
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->boolean('mine'), fn (Builder $q) => $q->whereHas('assignees', fn (Builder $a) => $a->whereKey($user->id)))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            // Designer review #77 / #88: filter by late ("failed") work.
            ->when($request->input('due') === 'overdue', fn (Builder $q) => $q->where('status', '!=', TaskStatus::Completed)->whereDate('due_date', '<', today()))
            ->when($request->input('due') === 'week', fn (Builder $q) => $q->whereBetween('due_date', [today(), today()->endOfWeek()]))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->input('priority')))
            ->orderBy('position')
            ->get();

        $projects = Project::visibleTo($user)->with('members')->orderBy('name')->get(['id', 'name']);

        return view('tasks.index', [
            'columns' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $s) => [$s->value => $tasks->where('status', $s)->values()]),
            'projects' => $projects,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create($request->user(), Project::findOrFail($request->integer('project_id')), $request->validated());

        return back()->with('status', __('Task ":title" created.', ['title' => $task->title]));
    }

    public function show(Request $request, Task $task): View
    {
        Gate::authorize('view', $task);

        $task->load(['project.members', 'creator', 'assignees', 'files.uploader', 'comments.user']);

        return view('tasks.show', [
            'task' => $task,
            'reviewer' => $task->assignees->first(fn ($u) => $u->pivot->is_reviewer),
            'canEdit' => $request->user()->can('update', $task),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($request->user(), $task, $request->validated());

        return back()->with('status', __('Task updated.'));
    }

    /** PATCH /tasks/{task}/move — called by the Kanban (fetch, JSON). */
    public function move(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->tasks->move($request->user(), $task, TaskStatus::from($request->validated('status')), $request->validated('position'));

        return response()->json(['data' => ['id' => $task->id, 'status' => $task->status->value, 'position' => $task->position]]);
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $this->tasks->delete($request->user(), $task);

        return redirect()->route('tasks.index')->with('status', __('Task deleted.'));
    }

    public function comment(Request $request, Task $task, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('view', $task);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $task->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $activity->log($request->user(), 'task.commented', "commented on \"{$task->title}\"", $task, $task->project);

        return back()->with('status', __('Comment added.'));
    }
}
