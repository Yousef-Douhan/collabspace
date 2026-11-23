<?php

namespace App\Http\Requests\Profile;

use App\Enums\Availability;
use App\Enums\Track;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'track' => ['sometimes', Rule::enum(Track::class)],
            'availability' => ['sometimes', Rule::enum(Availability::class)],
            'years_of_experience' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locale' => ['sometimes', Rule::in(['en', 'ar', 'fr'])],
        ];
    }
}
