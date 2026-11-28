<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'owner' => new UserSummaryResource($this->whenLoaded('owner')),
            // Pre-selected by the controller (no extra query per project); falls back to a lookup.
            'my_role' => $this->my_role ?? $this->roleOf($request->user())?->value,
            'members_count' => $this->whenCounted('members'),
            'tasks_count' => $this->whenCounted('tasks'),
            'completed_tasks_count' => $this->whenCounted('completed_tasks'),
            'completion_percentage' => $this->completion_percentage,
            'members' => UserSummaryResource::collection($this->whenLoaded('members')),
            // The wiki can be long: only sent on the single-project endpoint.
            'wiki' => $this->when($request->routeIs('api.v1.projects.show', 'api.v1.projects.wiki'), $this->wiki),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
