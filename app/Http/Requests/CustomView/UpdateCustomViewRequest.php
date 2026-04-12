<?php

namespace App\Http\Requests\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->workspace_id;

        return [
            'name'                 => ['sometimes', 'string', 'max:100'],
            'color'                => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'filters'              => ['sometimes', 'array'],
            'filters.status'       => ['nullable', 'in:open,pending,closed,spam,snoozed'],
            'filters.assigned'     => ['nullable', 'string', 'max:20'],
            'filters.mailbox_id'   => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'filters.tag_id'       => ['nullable', 'integer', Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
            'filters.priority'     => ['nullable', 'in:low,normal,high,urgent'],
            'filters.channel_type' => ['nullable', 'in:email,chat,whatsapp,slack,api,sms'],
        ];
    }
}
