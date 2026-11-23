<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'agenda' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:500'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'reminder_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10080'],
            'notify_channels' => ['nullable', 'array'],
            'notify_channels.*' => ['string', 'in:app,email,slack'],
            'transcript' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'attendees' => ['sometimes', 'nullable', 'array'],
            'attendees.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
