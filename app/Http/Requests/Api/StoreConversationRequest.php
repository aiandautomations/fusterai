<?php

namespace App\Http\Requests\Api;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject'        => ['required', 'string', 'max:500'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'mailbox_id'     => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $this->user()->workspace_id)],
            'priority'       => ['nullable', Rule::enum(ConversationPriority::class)],
            'status'         => ['nullable', Rule::enum(ConversationStatus::class)],
        ];
    }
}
