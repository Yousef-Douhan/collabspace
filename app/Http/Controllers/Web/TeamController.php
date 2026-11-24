<?php

namespace App\Http\Controllers\Web;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TeamRequest;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private TeamService $teams) {}

    public function index(Request $request): View
    {
        $teams = Team::query()
            ->with('members')
            ->withCount(['members', 'projects'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->orderBy('name')
            ->get();

        return view('teams.index', [
            'teams' => $teams,
            'users' => User::whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Team $team): View
    {
        $team->load(['members', 'projects', 'creator']);
        $user = $request->user();

        return view('teams.show', [
            'team' => $team,
            'canManage' => $user->can('update', $team),
            'users' => User::whereNull('deactivated_at')->whereNotIn('id', $team->members->pluck('id'))->orderBy('name')->get(['id', 'name']),
            // Projects this person may link the team to (they manage them, and it isn't linked yet).
            'projects' => Project::visibleTo($user)->whereNotIn('id', $team->projects->pluck('id'))->orderBy('name')->get()
                ->filter(fn (Project $p) => $p->isManagedBy($user))->values(),
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        Gate::authorize('create', Team::class);
        $team = $this->teams->create($request->user(), $request->validated());

        return redirect()->route('teams.show', $team)->with('status', __('Team created.'));
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);
        $this->teams->update($request->user(), $team, $request->validated());

        return back()->with('status', __('Team updated.'));
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);
        $this->teams->delete($request->user(), $team);

        return redirect()->route('teams.index')->with('status', __('Team deleted.'));
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'role' => ['nullable', Rule::enum(TeamRole::class)]]);

        $this->teams->addMember($request->user(), $team, User::findOrFail($data['user_id']), TeamRole::tryFrom($data['role'] ?? '') ?? TeamRole::Member);

        return back()->with('status', __('Member added.'));
    }

    public function updateMember(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);
        abort_unless($team->members()->whereKey($user->id)->exists(), 404);
        $data = $request->validate(['role' => ['required', Rule::enum(TeamRole::class)]]);

        $this->teams->changeRole($request->user(), $team, $user, TeamRole::from($data['role']));

        return back()->with('status', __('Role updated.'));
    }

    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);
        abort_unless($team->members()->whereKey($user->id)->exists(), 404);

        $this->teams->removeMember($request->user(), $team, $user);

        return back()->with('status', __('Member removed.'));
    }

    /** Link from either side: the team page or the project's Teams tab. */
    public function attachProject(Request $request, Team $team): RedirectResponse
    {
        $project = Project::findOrFail($request->validate(['project_id' => ['required', 'integer']])['project_id']);
        Gate::authorize('manageMembers', $project);

        $this->teams->attachToProject($request->user(), $team, $project);

        return back()->with('status', __('Team added to :project.', ['project' => $project->name]));
    }

    public function detachProject(Request $request, Team $team, Project $project): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $this->teams->detachFromProject($request->user(), $team, $project);

        return back()->with('status', __('Team removed from :project.', ['project' => $project->name]));
    }
}
