<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mailbox_id' => ['required', Rule::exists('mailboxes', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
