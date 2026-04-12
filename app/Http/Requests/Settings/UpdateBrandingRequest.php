<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branding_name'    => ['nullable', 'string', 'max:255'],
            'branding_website' => ['nullable', 'url', 'max:255'],
            'branding_logo'    => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp,svg'],
        ];
    }
}
