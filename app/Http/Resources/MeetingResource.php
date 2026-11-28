<?php

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Meeting */
class MeetingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $me = $this->relationLoaded('attendees')
            ? $this->attendees->firstWhere('id', $request->user()?->id)
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'agenda' => $this->agenda,
            'notes' => $this->notes,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'reminder_minutes' => $this->reminder_minutes,
            'project' => $this->whenLoaded('project', fn () => $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'attendees' => UserSummaryResource::collection($this->whenLoaded('attendees')),
            'attendees_count' => $this->whenLoaded('attendees', fn () => $this->attendees->count()),
            'my_response' => $me?->pivot->response,
            'has_transcript' => filled($this->transcript),
            // Long fields only on the single-meeting endpoints.
            'transcript' => $this->when(! $request->routeIs('api.v1.meetings.index'), $this->transcript),
            'ai_summary' => $this->ai_summary,
            'key_points' => $this->key_points ?? [],
            'comments_count' => $this->whenCounted('comments'),
            'attachments' => MeetingAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
