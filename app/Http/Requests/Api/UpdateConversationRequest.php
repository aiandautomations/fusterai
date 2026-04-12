<?php

namespace App\Http\Requests\Api;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'           => ['nullable', Rule::enum(ConversationStatus::class)],
            'priority'         => ['nullable', Rule::enum(ConversationPriority::class)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
