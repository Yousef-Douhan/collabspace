<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new UserSummaryResource($this->whenLoaded('sender')),
            'is_mine' => $this->sender_id === $request->user()?->id,
            'body' => $this->body,
            'attachment' => $this->attachment_path ? [
                'name' => $this->attachment_name,
                'type' => $this->attachment_type?->value,
                'url' => route('api.v1.messages.attachment', $this->resource),
            ] : null,
            'is_edited' => $this->edited_at !== null,
            'edited_at' => $this->edited_at?->toIso8601String(),
            // Ids of participants (other than the sender) who have read this message.
            'read_by' => $this->whenHas('read_by_ids'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
