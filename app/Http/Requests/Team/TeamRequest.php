<?php

namespace App\Http\Requests\Team;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST) or edit (PATCH / PUT) a team. Authorisation happens in the controller. */
class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $team = $this->route('team');
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80', Rule::unique('teams', 'name')->ignore($team instanceof Team ? $team->id : null)],
            'description' => ['nullable', 'string', 'max:500'],
            'members' => [$creating ? 'nullable' : 'prohibited', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ];
    }
}
