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
            'auto_reply_config.office_hours' => ['nullable', 'array'],
            'auto_reply_config.office_hours.enabled' => ['boolean'],
            'auto_reply_config.office_hours.timezone' => ['nullable', 'string', 'timezone'],
            'auto_reply_config.office_hours.subject' => ['nullable', 'string', 'max:255'],
            'auto_reply_config.office_hours.message' => ['nullable', 'string'],
            'auto_reply_config.office_hours.schedule' => ['nullable', 'array'],
            'auto_reply_config.office_hours.schedule.*' => ['nullable', 'array'],
            'auto_reply_config.office_hours.schedule.*.open' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'auto_reply_config.office_hours.schedule.*.close' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }
}
