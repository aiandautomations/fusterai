<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiveChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_id'  => ['required', 'integer', 'exists:workspaces,id'],
            'visitor_id'    => ['required', 'string', 'max:100'],
            'visitor_name'  => ['nullable', 'string', 'max:100'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'message'       => ['required', 'string', 'max:5000'],
        ];
    }
}
