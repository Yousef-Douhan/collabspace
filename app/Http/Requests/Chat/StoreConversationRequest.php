<?php

namespace App\Http\Requests\Chat;

use App\Enums\ConversationType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A project channel can only be created by a member of that project.
        if ($this->filled('project_id') && ($project = Project::find($this->integer('project_id')))) {
            return $this->user()->can('view', $project);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            // direct
            'user_id' => ['required_if:type,direct', 'nullable', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
            // channel
            'name' => ['required_if:type,channel', 'nullable', 'string', 'max:80'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'distinct', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
        ];
    }
}
