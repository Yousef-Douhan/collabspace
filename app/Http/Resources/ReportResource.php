<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'project' => $this->whenLoaded('project', fn () => $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'notes' => $this->notes,
            // Not called "data": a top-level "data" key would stop Laravel from wrapping the resource.
            'snapshot' => $this->data,
            'export_url' => route('api.v1.reports.export', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
