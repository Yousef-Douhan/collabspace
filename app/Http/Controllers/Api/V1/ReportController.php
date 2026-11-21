<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Project;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /** GET /reports/overview?project_id=&from=&to= — live numbers. */
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if (isset($data['project_id'])) {
            Gate::authorize('view', Project::findOrFail($data['project_id']));
        }

        return response()->json(['data' => $this->reports->overview(
            $request->user(),
            $data['project_id'] ?? null,
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null,
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null,
        )]);
    }

    /** GET /reports — saved reports (yours; admins see all). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $reports = Report::with(['project:id,name', 'creator'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('created_by', $user->id))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ReportResource::collection($reports);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $report = $this->reports->generate(
            $request->user(),
            ReportType::from($data['type']),
            $data['project_id'] ?? null,
            isset($data['period_start']) ? Carbon::parse($data['period_start'])->startOfDay() : null,
            isset($data['period_end']) ? Carbon::parse($data['period_end'])->endOfDay() : null,
            $data['notes'] ?? null,
        );

        return (new ReportResource($report->load(['project:id,name', 'creator'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Report $report): ReportResource
    {
        Gate::authorize('view', $report);

        return new ReportResource($report->load(['project:id,name', 'creator']));
    }

    public function destroy(Report $report): Response
    {
        Gate::authorize('delete', $report);

        $report->delete();

        return response()->noContent();
    }

    /** GET /reports/{report}/export — CSV of the saved snapshot (UTF-8 with BOM so Excel shows Arabic). */
    public function export(Report $report): StreamedResponse
    {
        Gate::authorize('view', $report);

        $rows = $report->data['rows'] ?? [];
        $filename = 'collabspace-'.$report->type->value.'-'.$report->created_at->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($rows === []) {
                fputcsv($out, ['metric', 'value']);
                foreach ($report->data['totals'] ?? [] as $metric => $value) {
                    fputcsv($out, [$metric, $value]);
                }
            } else {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, array_values($row));
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
