<?php

namespace App\Http\Requests\Api;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConversationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->workspace_id;

        return [
            'status' => ['nullable', Rule::enum(ConversationStatus::class)],
            'mailbox_id' => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'priority' => ['nullable', Rule::enum(ConversationPriority::class)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('workspace_id', $workspaceId)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
