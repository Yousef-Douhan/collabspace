<?php

namespace App\Http\Requests\Project;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any signed-in user can start a project (ProjectPolicy::create)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'max:30'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
