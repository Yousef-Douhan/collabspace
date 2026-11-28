<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWikiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateWiki', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'wiki' => ['present', 'nullable', 'string', 'max:100000'],
        ];
    }
}
