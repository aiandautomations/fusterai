<?php

namespace App\Http\Requests\Conversations;

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
        $workspaceId = $this->user()->workspace_id;

        return [
            'mailbox_id'  => ['required', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'subject'     => ['required', 'string', 'max:255'],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('workspace_id', $workspaceId)],
            'body'        => ['required', 'string'],
        ];
    }
}
