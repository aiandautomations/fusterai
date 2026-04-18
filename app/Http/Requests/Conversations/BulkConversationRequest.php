<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ConversationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:close,reopen,assign,snooze,spam,priority,mark_read,mark_unread'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('workspace_id', $this->user()->workspace_id)],
            'snooze_until' => ['nullable', 'date', 'after:now'],
            'priority' => ['nullable', Rule::enum(ConversationPriority::class)],
        ];
    }
}
