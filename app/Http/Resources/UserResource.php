<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'job_title' => $this->job_title,
            'track' => $this->track?->value,
            'role' => $this->role?->value,
            'availability' => $this->availability?->value,
            'years_of_experience' => $this->years_of_experience,
            'about' => $this->about,
            'locale' => $this->locale,
            'joined_at' => $this->joined_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
