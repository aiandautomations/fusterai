<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCannedResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100'],
            'content'    => ['required', 'string'],
            'mailbox_id' => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
