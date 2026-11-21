<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Team directory and member profiles ("Profile / overview · tasks · files"). */
class UserController extends Controller
{
    /** GET /users?q=&track=&availability= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('q'), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('track'), fn (Builder $q) => $q->where('track', $request->input('track')))
            ->when($request->filled('availability'), fn (Builder $q) => $q->where('availability', $request->input('availability')))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return UserSummaryResource::collection($users);
    }

    /** GET /users/{user} — profile overview with stats and their open tasks in projects the viewer can see. */
    public function show(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        $visibleTasks = $user->tasks()->whereHas('project', fn (Builder $q) => $q->visibleTo($viewer));

        return response()->json([
            'data' => [
                ...(new UserResource($user))->resolve($request),
                'stats' => [
                    'projects' => $user->projects()->count(),
                    'open_tasks' => (clone $visibleTasks)->where('status', '!=', TaskStatus::Completed)->count(),
                    'completed_tasks' => (clone $visibleTasks)->where('status', TaskStatus::Completed)->count(),
                    'files_uploaded' => $user->uploadedFiles()->count(),
                ],
                'shared_projects' => Project::visibleTo($viewer)
                    ->whereHas('members', fn (Builder $q) => $q->whereKey($user->id))
                    ->get(['projects.id', 'projects.name'])
                    ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name]),
                'open_tasks' => TaskResource::collection(
                    (clone $visibleTasks)->where('status', '!=', TaskStatus::Completed)
                        ->with('project:id,name')
                        ->orderBy('due_date')
                        ->limit(10)
                        ->get()
                ),
            ],
        ]);
    }
}
