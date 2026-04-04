<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Crypt;
use Laravel\Ai\Enums\Lab;

class AiSettingsService
{
    /**
     * Apply workspace AI credentials to runtime config and return the
     * Lab enum + model string for passing to agent prompt() / broadcast() calls.
     *
     * Call this at the top of every AI job handle() and in the web middleware
     * so agents always use the admin-configured provider — not .env defaults.
     *
     * @return array{lab: Lab, model: string|null}
     */
    public function configureForWorkspace(int $workspaceId): array
    {
        $workspace = Workspace::findOrFail($workspaceId);
        /** @var array<string, mixed> $settings */
        $settings  = $workspace->settings ?? [];

        $provider = $settings['ai_provider'] ?? 'anthropic';
        $model    = $settings['ai_model'] ?? null;
        $baseUrl  = $settings['ai_base_url'] ?? null;

        // Decrypt and inject the API key into the runtime config.
        // config()->set() is the correct way to override config values for the
        // current process — the Laravel AI SDK reads these when resolving drivers.
        if (! empty($settings['ai_api_key'])) {
            try {
                $key = Crypt::decryptString($settings['ai_api_key']);
            } catch (\Exception) {
                $key = null;
            }

            if ($key) {
                match ($provider) {
                    'openai', 'openai-compatible' => config(['ai.providers.openai.key' => $key]),
                    'openrouter'                  => config(['ai.providers.openrouter.key' => $key]),
                    default                       => config(['ai.providers.anthropic.key' => $key]),
                };
            }
        }

        // For OpenAI-compatible endpoints (Groq, Together, Ollama…),
        // override the openai driver's base URL.
        if ($provider === 'openai-compatible' && $baseUrl) {
            config(['ai.providers.openai.url' => $baseUrl]);
        }

        // OpenRouter: fixed base URL, no custom base_url needed.
        // The openrouter provider entry in config/ai.php already sets the URL.
        // We just inject the API key above and use Lab::OpenAI to select the openai driver.

        // Map provider name to the Lab enum the SDK uses for driver selection.
        $lab = match ($provider) {
            'anthropic'  => Lab::Anthropic,
            'openrouter' => Lab::OpenAI, // openrouter driver is openai-compatible
            default      => Lab::OpenAI, // covers 'openai' and 'openai-compatible'
        };

        return ['lab' => $lab, 'model' => $model];
    }

    /**
     * Check whether a workspace-level AI feature flag is enabled.
     * Falls back to config default so behaviour is consistent across all
     * inbound channels (email, webhook, WhatsApp, live chat).
     */
    public function isFeatureEnabled(int $workspaceId, string $feature): bool
    {
        $workspace = Workspace::find($workspaceId);
        $wsFeatures = $workspace?->settings['ai_features'] ?? [];

        return (bool) ($wsFeatures[$feature] ?? config("ai.features.{$feature}", true));
    }

    /**
     * Return sanitised AI config for the frontend.
     * The API key is never exposed — only whether one has been saved.
     */
    public function getForWorkspace(int $workspaceId): array
    {
        $workspace = Workspace::findOrFail($workspaceId);
        /** @var array<string, mixed> $settings */
        $settings  = $workspace->settings ?? [];

        return [
            'provider' => $settings['ai_provider'] ?? 'anthropic',
            'model'    => $settings['ai_model']    ?? null,
            'base_url' => $settings['ai_base_url'] ?? null,
            'key_set'  => ! empty($settings['ai_api_key']),
            'features' => $settings['ai_features'] ?? [
                'reply_suggestions'   => true,
                'auto_categorization' => true,
                'summarization'       => true,
            ],
            'rag' => $settings['ai_rag'] ?? [
                'top_k'     => 5,
                'min_score' => 0.7,
            ],
        ];
    }

    /**
     * Validate and persist AI settings for a workspace.
     * The API key is encrypted with AES-256-CBC (via APP_KEY) before storage.
     * Passing an empty api_key keeps the existing encrypted key unchanged.
     */
    public function saveForWorkspace(int $workspaceId, array $validated): void
    {
        $workspace = Workspace::findOrFail($workspaceId);
        /** @var array<string, mixed> $settings */
        $settings  = $workspace->settings ?? [];

        $settings['ai_provider'] = $validated['provider'];
        $settings['ai_model']    = $validated['model'] ?? null;

        // base_url is only meaningful for openai-compatible; clear it otherwise
        if ($validated['provider'] === 'openai-compatible') {
            $settings['ai_base_url'] = $validated['base_url'] ?? null;
        } else {
            unset($settings['ai_base_url']);
        }

        // Only replace the stored key when the admin actually typed a new one
        if (! empty($validated['api_key'])) {
            $settings['ai_api_key'] = Crypt::encryptString($validated['api_key']);
        }

        $settings['ai_features'] = [
            'reply_suggestions'   => (bool) ($validated['feature_reply_suggestions']   ?? true),
            'auto_categorization' => (bool) ($validated['feature_auto_categorization'] ?? true),
            'summarization'       => (bool) ($validated['feature_summarization']        ?? true),
        ];

        $settings['ai_rag'] = [
            'top_k'     => (int)   ($validated['rag_top_k']     ?? 5),
            'min_score' => (float) ($validated['rag_min_score'] ?? 0.7),
        ];

        $workspace->settings = $settings;
        $workspace->save();
    }
}
