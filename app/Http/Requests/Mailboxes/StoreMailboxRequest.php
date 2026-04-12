<?php

namespace App\Http\Requests\Mailboxes;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
        ];
    }
}
