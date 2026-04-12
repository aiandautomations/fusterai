<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode'     => ['required', 'in:light,dark,system'],
            'color'    => ['required', 'in:neutral,amber,blue,cyan,emerald,fuchsia,green,indigo,lime,orange,pink,purple,red,rose,sky,teal,violet,yellow'],
            'font'     => ['required', 'in:inter,figtree,manrope,system'],
            'radius'   => ['required', 'in:sm,md,lg,xl'],
            'contrast' => ['required', 'in:soft,balanced,strong'],
        ];
    }
}
