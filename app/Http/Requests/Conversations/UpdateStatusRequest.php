<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ConversationStatus::class)],
        ];
    }
}
