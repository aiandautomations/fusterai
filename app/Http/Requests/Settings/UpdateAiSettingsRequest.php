<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'                    => ['required', 'in:anthropic,openai,openai-compatible,openrouter'],
            'api_key'                     => ['nullable', 'string', 'max:500'],
            'model'                       => ['nullable', 'string', 'max:100'],
            'base_url'                    => ['nullable', 'url', 'max:255'],
            'feature_reply_suggestions'   => ['boolean'],
            'feature_auto_categorization' => ['boolean'],
            'feature_summarization'       => ['boolean'],
            'rag_top_k'                   => ['integer', 'min:1', 'max:10'],
            'rag_min_score'               => ['numeric', 'min:0', 'max:1'],
        ];
    }
}
