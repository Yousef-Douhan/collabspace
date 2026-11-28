<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Requests\Project\UpdateWikiRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    /** GET /projects?status=&priority=&q=&per_page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $projects = $this->withStats(Project::visibleTo($user), $user->id)
            ->with('owner')
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate($this->perPage($request));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projects->create($request->user(), $request->validated());

        return $this->show($request, $project)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        $project = $this->withStats(Project::whereKey($project->id), $request->user()->id)
            ->with(['owner', 'members'])
            ->firstOrFail();

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->projects->update($request->user(), $project, $request->validated());

        return $this->show($request, $project);
    }

    public function destroy(Request $request, Project $project): Response
    {
        Gate::authorize('delete', $project);

        $this->projects->delete($request->user(), $project);

        return response()->noContent();
    }

    /** PUT /projects/{project}/wiki */
    public function updateWiki(UpdateWikiRequest $request, Project $project): ProjectResource
    {
        $this->projects->updateWiki($request->user(), $project, $request->validated('wiki'));

        return $this->show($request, $project);
    }

    /**
     * Adds members/tasks counters and the viewer's role in one query.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withStats(Builder $query, int $userId): Builder
    {
        return $query
            ->withCount([
                'members',
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
            ])
            ->addSelect(['my_role' => DB::table('project_members')
                ->select('role')
                ->whereColumn('project_members.project_id', 'projects.id')
                ->where('project_members.user_id', $userId)
                ->limit(1),
            ]);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
