<?php

namespace App\Http\Requests\Meeting;

use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->filled('project_id') ? Project::find($this->integer('project_id')) : null;

        return $this->user()->can('create', [Meeting::class, $project]);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'notify_channels' => ['nullable', 'array'],
            'notify_channels.*' => ['string', 'in:app,email,slack'],
            'transcript' => ['nullable', 'string', 'max:200000'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
