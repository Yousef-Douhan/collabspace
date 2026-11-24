<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Teams: CRUD, members (lead / member) and linking teams to projects. */
class TeamController extends Controller
{
    public function __construct(private TeamService $teams) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TeamResource::collection(
            Team::withCount(['members', 'projects'])
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
                ->orderBy('name')->get()
        );
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team->load(['members', 'projects'])->loadCount(['members', 'projects']));
    }

    public function store(TeamRequest $request): JsonResponse
    {
        Gate::authorize('create', Team::class);
        $team = $this->teams->create($request->user(), $request->validated());

        return (new TeamResource($team->load('members')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        Gate::authorize('update', $team);

        return new TeamResource($this->teams->update($request->user(), $team, $request->validated())->load('members'));
    }

    public function destroy(Request $request, Team $team): Response
    {
        Gate::authorize('delete', $team);
        $this->teams->delete($request->user(), $team);

        return response()->noContent();
    }

    public function addMember(Request $request, Team $team): TeamResource
    {
        Gate::authorize('manageMembers', $team);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'role' => ['nullable', Rule::enum(TeamRole::class)]]);

        $this->teams->addMember($request->user(), $team, User::findOrFail($data['user_id']), TeamRole::tryFrom($data['role'] ?? '') ?? TeamRole::Member);

        return new TeamResource($team->load('members'));
    }

    public function updateMember(Request $request, Team $team, User $user): TeamResource
    {
        Gate::authorize('manageMembers', $team);
        abort_unless($team->members()->whereKey($user->id)->exists(), 404);
        $data = $request->validate(['role' => ['required', Rule::enum(TeamRole::class)]]);

        $this->teams->changeRole($request->user(), $team, $user, TeamRole::from($data['role']));

        return new TeamResource($team->load('members'));
    }

    public function removeMember(Request $request, Team $team, User $user): Response
    {
        Gate::authorize('manageMembers', $team);
        abort_unless($team->members()->whereKey($user->id)->exists(), 404);

        $this->teams->removeMember($request->user(), $team, $user);

        return response()->noContent();
    }

    /** POST /projects/{project}/teams  { team_id } */
    public function attach(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);
        $team = Team::findOrFail($request->validate(['team_id' => ['required', 'integer']])['team_id']);

        $this->teams->attachToProject($request->user(), $team, $project);

        return response()->json(['data' => TeamResource::collection($project->teams()->withCount('members')->get())], Response::HTTP_CREATED);
    }

    /** DELETE /projects/{project}/teams/{team} */
    public function detach(Request $request, Project $project, Team $team): Response
    {
        Gate::authorize('manageMembers', $project);
        $this->teams->detachFromProject($request->user(), $team, $project);

        return response()->noContent();
    }
}
