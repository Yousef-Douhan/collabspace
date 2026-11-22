<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $projectId = $request->integer('project_id') ?: null;
        if ($projectId) {
            Gate::authorize('view', Project::findOrFail($projectId));
        }

        return view('dashboard.index', [
            'd' => $dashboard->build($request->user(), $projectId, $request->input('period', 'all')),
            'projects' => Project::visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'projectId' => $projectId,
        ]);
    }
}
