<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddMemberRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Requests\Project\UpdateWikiRequest;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(Request $request): View
    {
        $projects = Project::visibleTo($request->user())
            ->with('members')
            ->withCount([
                'files',
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
            ])
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->input('sort') === 'oldest', fn (Builder $q) => $q->oldest(), fn (Builder $q) => $q->latest())
            ->get();

        return view('projects.index', [
            'projects' => $projects,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projects->create($request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('status', __('Project created.'));
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['owner', 'members', 'files.uploader', 'tasks.assignees', 'teams' => fn ($q) => $q->withCount('members')])
            ->loadCount(['tasks', 'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed)]);

        $byStatus = $project->tasks->groupBy(fn ($t) => $t->status->value);

        return view('projects.show', [
            'project' => $project,
            'tab' => in_array($request->input('tab'), ['overview', 'tasks', 'teams', 'files'], true) ? $request->input('tab') : 'overview',
            'byStatus' => $byStatus,
            'canManage' => $project->isManagedBy($request->user()),
            'users' => User::whereNotIn('id', $project->members->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'roles' => ProjectRole::cases(),
            'availableTeams' => Team::withCount('members')->whereNotIn('id', $project->teams->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projects->update($request->user(), $project, $request->validated());

        return back()->with('status', __('Project updated.'));
    }

    public function updateWiki(UpdateWikiRequest $request, Project $project): RedirectResponse
    {
        $this->projects->updateWiki($request->user(), $project, $request->validated('wiki'));

        return back()->with('status', __('Wiki saved.'));
    }

    public function addMember(AddMemberRequest $request, Project $project): RedirectResponse
    {
        $this->projects->addMember(
            $request->user(),
            $project,
            User::findOrFail($request->integer('user_id')),
            ProjectRole::from($request->input('role', ProjectRole::Member->value)),
        );

        return back()->with('status', __('Member added.'));
    }

    public function removeMember(Request $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $this->projects->removeMember($request->user(), $project, $user);

        return back()->with('status', __(':name was removed from the project.', ['name' => $user->name]));
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $this->projects->delete($request->user(), $project);

        return redirect()->route('projects.index')->with('status', __('Project deleted.'));
    }
}
