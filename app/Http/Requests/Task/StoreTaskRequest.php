<?php

namespace App\Http\Requests\Task;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->integer('project_id'));

        // Unknown project → let validation report it; otherwise check membership.
        return $project === null || $this->user()->can('create', [\App\Models\Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'track' => ['required', Rule::enum(Track::class)],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'estimated_hours' => ['nullable', 'integer', 'between:0,1000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignees' => ['nullable', 'array'],
            // Assignees must be members of the task's project.
            'assignees.*' => [
                'integer',
                'distinct',
                Rule::exists('project_members', 'user_id')->where('project_id', $this->integer('project_id')),
            ],
            // Who reviews the task when it reaches "In Review" (must be a project member).
            'reviewer_id' => ['nullable', 'integer', Rule::exists('project_members', 'user_id')->where('project_id', $this->integer('project_id'))],
        ];
    }

    public function messages(): array
    {
        return [
            'assignees.*.exists' => 'Each assignee must be a member of the project.',
        ];
    }
}
