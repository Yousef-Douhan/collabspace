<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Availability;
use App\Enums\MeetingResponse;
use App\Enums\Priority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Enums\Track;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /meta — every enum with its labels, so clients never hard-code them. */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'task_statuses' => TaskStatus::options(),
            'priorities' => Priority::options(),
            'tracks' => Track::options(),
            'availability' => Availability::options(),
            'project_statuses' => ProjectStatus::options(),
            'project_roles' => ProjectRole::options(),
            'meeting_responses' => MeetingResponse::options(),
            'report_types' => ReportType::options(),
        ]]);
    }
}
