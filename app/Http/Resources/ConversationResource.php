<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $state = $this->pivot ?? $this->participants->firstWhere('id', $viewer->id)?->pivot;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->displayNameFor($viewer),
            'project_id' => $this->project_id,
            'participants' => UserSummaryResource::collection($this->whenLoaded('participants')),
            'is_pinned' => (bool) ($state->is_pinned ?? false),
            'is_archived' => (bool) ($state->is_archived ?? false),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
