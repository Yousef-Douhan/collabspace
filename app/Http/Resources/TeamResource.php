<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'members_count' => $this->whenCounted('members'),
            'projects_count' => $this->whenCounted('projects'),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                ...(new UserSummaryResource($m))->toArray($request),
                'team_role' => $m->pivot->role,
            ])),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
