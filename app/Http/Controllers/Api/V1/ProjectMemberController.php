<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddMemberRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return UserSummaryResource::collection($project->members()->orderBy('name')->get());
    }

    public function store(AddMemberRequest $request, Project $project): AnonymousResourceCollection
    {
        $member = User::findOrFail($request->integer('user_id'));
        $role = ProjectRole::from($request->validated('role') ?? ProjectRole::Member->value);

        $this->projects->addMember($request->user(), $project, $member, $role);

        return $this->index($project);
    }

    public function destroy(Request $request, Project $project, User $user): Response
    {
        Gate::authorize('manageMembers', $project);

        $this->projects->removeMember($request->user(), $project, $user);

        return response()->noContent();
    }
}
