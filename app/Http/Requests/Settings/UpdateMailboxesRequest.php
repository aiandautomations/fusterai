<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailboxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mailbox_ids' => ['nullable', 'array'],
            'mailbox_ids.*' => ['integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
