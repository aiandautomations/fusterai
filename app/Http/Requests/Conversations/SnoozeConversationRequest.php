<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'until' => ['required', 'date', 'after:now'],
        ];
    }
}
