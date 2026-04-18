<?php

namespace App\Http\Requests\Mailboxes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email'],
            'signature' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'imap_config' => ['nullable', 'array'],
            'smtp_config' => ['nullable', 'array'],
            'auto_reply_config' => ['nullable', 'array'],
            'auto_reply_config.enabled' => ['boolean'],
            'auto_reply_config.subject' => ['nullable', 'string', 'max:255'],
            'auto_reply_config.body' => ['nullable', 'string'],
            'auto_reply_config.auto_close_pending_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
