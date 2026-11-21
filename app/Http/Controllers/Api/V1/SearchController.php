<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /search?q= — the "Search Bar overlay": top matches per type, limited to what the user can see. */
class SearchController extends Controller
{
    private const PER_TYPE = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $data['q']).'%';
        $user = $request->user();

        return response()->json(['data' => [
            'projects' => Project::visibleTo($user)->where('name', 'like', $like)->limit(self::PER_TYPE)->get()
                ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'status' => $p->status->value]),
            'tasks' => Task::visibleTo($user)->with('project:id,name')->where('title', 'like', $like)->limit(self::PER_TYPE)->get()
                ->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'status' => $t->status->value, 'project' => $t->project->name]),
            'files' => ProjectFile::visibleTo($user)->with('project:id,name')->where('original_name', 'like', $like)->limit(self::PER_TYPE)->get()
                ->map(fn (ProjectFile $f) => ['id' => $f->id, 'name' => $f->original_name, 'project' => $f->project->name]),
            'meetings' => Meeting::visibleTo($user)->where('title', 'like', $like)->orderByDesc('starts_at')->limit(self::PER_TYPE)->get()
                ->map(fn (Meeting $m) => ['id' => $m->id, 'title' => $m->title, 'starts_at' => $m->starts_at->toIso8601String()]),
            'people' => User::where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('job_title', 'like', $like))
                ->limit(self::PER_TYPE)->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'job_title' => $u->job_title]),
        ]]);
    }
}
