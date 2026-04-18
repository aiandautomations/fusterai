<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger' => ['required', 'string', 'in:conversation.created,conversation.replied,conversation.closed,conversation.assigned,time.idle'],
            'conditions' => ['array'],
            'actions' => ['required', 'array', 'min:1'],
            'active' => ['boolean'],
        ];
    }
}
