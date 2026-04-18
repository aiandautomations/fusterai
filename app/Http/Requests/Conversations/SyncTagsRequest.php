<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag_ids' => ['array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
