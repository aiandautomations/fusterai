<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'max:100'],
            'name'           => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:255', 'unique:users'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
