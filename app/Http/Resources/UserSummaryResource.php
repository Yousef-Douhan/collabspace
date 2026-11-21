<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact user shape used inside other resources (assignees, owner, uploader…).
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'job_title' => $this->job_title,
            'track' => $this->track?->value,
            'availability' => $this->availability?->value,
            'project_role' => $this->whenPivotLoaded('project_members', fn () => $this->pivot->role),
            'is_reviewer' => $this->whenPivotLoaded('task_user', fn () => (bool) $this->pivot->is_reviewer),
        ];
    }
}
