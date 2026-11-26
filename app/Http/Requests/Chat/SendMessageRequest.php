<?php

namespace App\Http\Requests\Chat;

use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:20480', new SafeUpload],
        ];
    }
}
