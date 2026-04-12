<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'name'     => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
