<?php

namespace App\Http\Requests\Mailboxes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number_id' => ['required', 'string'],
            'access_token' => ['required', 'string'],
            'app_secret' => ['required', 'string'],
        ];
    }
}
