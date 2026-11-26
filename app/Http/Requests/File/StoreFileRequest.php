<?php

namespace App\Http\Requests\File;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->integer('project_id'));

        return $project === null || $this->user()->can('create', [ProjectFile::class, $project]);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            // The task (optional) must belong to the same project.
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->integer('project_id'))],
            'file' => ['required', 'file', 'max:20480', new SafeUpload], // 20 MB
        ];
    }
}
