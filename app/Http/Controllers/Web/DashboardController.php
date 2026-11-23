<?php

namespace App\Http\Controllers\Web;

use App\Enums\Track;
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

        $period = in_array($request->input('period'), ['today', 'week', 'month', 'quarter', 'year'], true) ? $request->input('period') : 'year';
        $track = Track::tryFrom((string) $request->input('track'))?->value;

        return view('dashboard.index', [
            'd' => $dashboard->build($request->user(), $projectId, $period, $track),
            'projects' => Project::visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'projectId' => $projectId,
        ]);
    }
}
