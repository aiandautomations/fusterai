<?php

namespace App\Http\Requests\Conversations;

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
        return [
            'status' => ['nullable', Rule::in([...array_column(ConversationStatus::cases(), 'value'), 'snoozed'])],
            'priority' => ['nullable', Rule::enum(ConversationPriority::class)],
            'mailbox' => ['nullable', 'integer'],
            'assigned' => ['nullable', 'regex:/^(me|none|all|\d+)$/'],
            'tag' => ['nullable', 'integer'],
            'folder' => ['nullable', 'integer'],
            'view' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'starred' => ['nullable', 'boolean'],
        ];
    }
}
