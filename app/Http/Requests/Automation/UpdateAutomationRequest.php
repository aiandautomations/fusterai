<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationRequest extends FormRequest
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
            'trigger' => ['required', 'string'],
            'conditions' => ['array'],
            'actions' => ['required', 'array', 'min:1'],
            'active' => ['boolean'],
        ];
    }
}
