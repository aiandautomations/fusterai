<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ThreadType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
            'type' => ['required', Rule::in([ThreadType::Message->value, ThreadType::Note->value])],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }
}
