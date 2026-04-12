<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ConversationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(ConversationPriority::class)],
        ];
    }
}
