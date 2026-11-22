<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Project;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if (isset($data['project_id'])) {
            Gate::authorize('view', Project::findOrFail($data['project_id']));
        }

        return view('reports.index', [
            'o' => $this->reports->overview(
                $user,
                $data['project_id'] ?? null,
                isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null,
                isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null,
            ),
            'projects' => Project::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'saved' => Report::with('project:id,name')
                ->when(! $user->isAdmin(), fn ($q) => $q->where('created_by', $user->id))
                ->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->reports->generate(
            $request->user(),
            ReportType::from($data['type']),
            $data['project_id'] ?? null,
            isset($data['period_start']) ? Carbon::parse($data['period_start'])->startOfDay() : null,
            isset($data['period_end']) ? Carbon::parse($data['period_end'])->endOfDay() : null,
            $data['notes'] ?? null,
        );

        return back()->with('status', __('Report submitted.'));
    }

    public function destroy(Report $report): RedirectResponse
    {
        Gate::authorize('delete', $report);

        $report->delete();

        return back()->with('status', __('Report deleted.'));
    }
}
