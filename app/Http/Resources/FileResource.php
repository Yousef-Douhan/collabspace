<?php

namespace App\Http\Resources;

use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectFile */
class FileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size' => $this->formatted_size,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'task_id' => $this->task_id,
            'uploader' => new UserSummaryResource($this->whenLoaded('uploader')),
            'download_url' => route('api.v1.files.download', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
