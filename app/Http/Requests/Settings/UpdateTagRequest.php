<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->workspace_id;
        $tagId       = $this->route('tag')?->id;

        return [
            'name'  => ['sometimes', 'string', 'max:50', "unique:tags,name,{$tagId},id,workspace_id,{$workspaceId}"],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
