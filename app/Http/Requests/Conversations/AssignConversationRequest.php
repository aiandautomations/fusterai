<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
