<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLiveChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'greeting' => ['required', 'string', 'max:200'],
            'color' => ['required', 'string', 'max:20'],
            'position' => ['required', 'in:bottom-right,bottom-left'],
            'launcher_text' => ['required', 'string', 'max:60'],
        ];
    }
}
