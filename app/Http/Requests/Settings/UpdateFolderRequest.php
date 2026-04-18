<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
