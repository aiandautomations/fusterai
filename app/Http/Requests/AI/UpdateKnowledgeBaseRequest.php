<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ];
    }
}
