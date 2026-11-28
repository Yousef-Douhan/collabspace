<?php

namespace App\Http\Requests\Auth;

use App\Enums\Track;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'track' => ['nullable', Rule::enum(Track::class)],
            'locale' => ['nullable', Rule::in(['en', 'ar', 'fr'])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
