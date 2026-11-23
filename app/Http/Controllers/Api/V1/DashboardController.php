<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Track;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /** GET /dashboard?project_id=&period=today|week|month|quarter|year|all&track=ui_ux|front_end|back_end|graphic_design */
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'period' => ['nullable', 'in:today,week,month,quarter,year,all'],
            'track' => ['nullable', Rule::enum(Track::class)],
        ]);

        if (isset($data['project_id'])) {
            Gate::authorize('view', Project::findOrFail($data['project_id']));
        }

        return response()->json([
            'data' => $dashboard->build($request->user(), $data['project_id'] ?? null, $data['period'] ?? 'month', $data['track'] ?? null),
        ]);
    }
}
