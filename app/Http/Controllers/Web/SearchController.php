<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $results = [];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $user = $request->user();

            $results = [
                'projects' => Project::visibleTo($user)->where('name', 'like', $like)->limit(8)->get(),
                'tasks' => Task::visibleTo($user)->with('project:id,name')->where('title', 'like', $like)->limit(8)->get(),
                'files' => ProjectFile::visibleTo($user)->with('project:id,name')->where('original_name', 'like', $like)->limit(8)->get(),
                'meetings' => Meeting::visibleTo($user)->where('title', 'like', $like)->orderByDesc('starts_at')->limit(8)->get(),
                'people' => User::where(fn (Builder $b) => $b->where('name', 'like', $like)->orWhere('job_title', 'like', $like))->limit(8)->get(),
            ];
        }

        return view('search.index', ['q' => $q, 'results' => $results]);
    }
}
