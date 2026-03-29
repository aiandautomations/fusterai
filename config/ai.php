<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */
    'default' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key'    => env('ANTHROPIC_API_KEY'),
            'url'    => env('ANTHROPIC_BASE_URL'),
        ],
        'openai' => [
            'driver' => 'openai',
            'key'    => env('OPENAI_API_KEY'),
            'url'    => env('OPENAI_BASE_URL'),
        ],
        // OpenAI-compatible endpoint slot — used at runtime for Groq,
        // Together, Ollama, and any other provider that speaks the OpenAI API format.
        // Credentials and base URL are injected from workspace.settings by
        // AiSettingsService::configureForWorkspace() — ENV vars are fallback only.
        'openai-compatible' => [
            'driver' => 'openai',
            'key'    => env('OPENAI_COMPATIBLE_API_KEY', ''),
            'url'    => env('OPENAI_COMPATIBLE_BASE_URL', ''),
        ],

        // OpenRouter — routes requests to 200+ models (Claude, GPT-4o, Gemini, Llama…)
        // via a single OpenAI-compatible API. Set OPENROUTER_API_KEY in .env and choose
        // any model slug from openrouter.ai/models (e.g. "anthropic/claude-3.5-sonnet").
        'openrouter' => [
            'driver' => 'openai',
            'key'    => env('OPENROUTER_API_KEY', ''),
            'url'    => 'https://openrouter.ai/api/v1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'reply_suggestions'   => env('AI_REPLY_SUGGESTIONS', true),
        'auto_categorization' => env('AI_AUTO_CATEGORIZATION', true),
        'summarization'       => env('AI_SUMMARIZATION', true),
        'rag'                 => env('AI_RAG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG Settings
    |--------------------------------------------------------------------------
    */
    'rag' => [
        'chunk_size'    => 1000,
        'chunk_overlap' => 200,
        'top_k'         => 5,
        'min_score'     => 0.7,
        'embedding_dimensions' => 1536,
    ],

];
