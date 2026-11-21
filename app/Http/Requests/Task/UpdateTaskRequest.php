<?php

namespace App\Http\Requests\Task;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'track' => ['sometimes', Rule::enum(Track::class)],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'estimated_hours' => ['sometimes', 'nullable', 'integer', 'between:0,1000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assignees' => ['sometimes', 'nullable', 'array'],
            'assignees.*' => [
                'integer',
                'distinct',
                Rule::exists('project_members', 'user_id')->where('project_id', $task->project_id),
            ],
            'reviewer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('project_members', 'user_id')->where('project_id', $task->project_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'assignees.*.exists' => 'Each assignee must be a member of the project.',
        ];
    }
}
