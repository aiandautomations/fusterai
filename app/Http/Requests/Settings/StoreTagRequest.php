<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->workspace_id;

        return [
            'name'  => ['required', 'string', 'max:50', "unique:tags,name,NULL,id,workspace_id,{$workspaceId}"],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
