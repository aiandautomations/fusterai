<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'into_id' => ['required', Rule::exists('conversations', 'id')->where('workspace_id', $this->user()->workspace_id)],
        ];
    }
}
