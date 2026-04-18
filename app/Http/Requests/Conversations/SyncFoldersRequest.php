<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFoldersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_ids' => ['array'],
            'folder_ids.*' => [Rule::exists('folders', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
