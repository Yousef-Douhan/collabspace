<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'track' => $this->track?->value,
            'progress' => $this->progress,
            'position' => $this->position,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'assignees' => UserSummaryResource::collection($this->whenLoaded('assignees')),
            'reviewer' => $this->whenLoaded('assignees', function () {
                $reviewer = $this->assignees->first(fn ($u) => (bool) $u->pivot->is_reviewer);

                return $reviewer ? new UserSummaryResource($reviewer) : null;
            }),
            'estimated_hours' => $this->estimated_hours,
            'files_count' => $this->whenCounted('files'),
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
