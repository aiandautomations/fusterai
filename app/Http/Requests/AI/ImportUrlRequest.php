<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class ImportUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
        ];
    }
}
