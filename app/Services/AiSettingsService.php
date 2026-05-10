<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Laravel\Ai\Enums\Lab;

class AiSettingsService
{
    /**
     * Resolve workspace AI credentials without any side effects.
     *
     * @return array{lab: Lab, model: string|null, key: string|null, provider: string, base_url: string|null}
     */
    public function resolveCredentials(int $workspaceId): array
    {
        $settings = Cache::remember(
            "workspace.ai_settings.{$workspaceId}",
            now()->addMinutes(5),
            fn () => Workspace::findOrFail($workspaceId)->settings ?? [],
        );

        $provider = $settings['ai_provider'] ?? 'anthropic';
        $model = $settings['ai_model'] ?? null;
        $baseUrl = $settings['ai_base_url'] ?? null;
        $key = null;

        if (! empty($settings['ai_api_key'])) {
            try {
                $key = Crypt::decryptString($settings['ai_api_key']);
            } catch (\Exception) {
                $key = null;
            }
        }

        $lab = match ($provider) {
            'anthropic' => Lab::Anthropic,
            'openrouter' => Lab::OpenAI,
            default => Lab::OpenAI,
        };

        return ['lab' => $lab, 'model' => $model, 'key' => $key, 'provider' => $provider, 'base_url' => $baseUrl];
    }

    /**
     * Run a callback with workspace AI credentials injected into config, then
     * restore all original config values in a finally block.
     *
     * Octane-safe: config changes are scoped to the closure execution window
     * and always restored — no permanent global config mutation.
     *
     * Usage in AI jobs:
     *   $aiSettings->withWorkspaceCredentials($workspaceId, function (Lab $lab, ?string $model) {
     *       (new MyAgent)->prompt('...', provider: $lab, model: $model);
     *   });
     *
     * @template TReturn
     *
     * @param  callable(Lab, string|null): TReturn  $callback
     * @return TReturn
     */
    public function withWorkspaceCredentials(int $workspaceId, callable $callback): mixed
    {
        $creds = $this->resolveCredentials($workspaceId);

        // Determine which config keys will be mutated so we can restore them
        $configKey = match ($creds['provider']) {
            'openai', 'openai-compatible' => 'ai.providers.openai.key',
            'openrouter' => 'ai.providers.openrouter.key',
            default => 'ai.providers.anthropic.key',
        };

        $originalKey = config($configKey);
        $originalUrl = config('ai.providers.openai.url');

        try {
            if ($creds['key']) {
                config([$configKey => $creds['key']]);
            }
            if ($creds['provider'] === 'openai-compatible' && $creds['base_url']) {
                config(['ai.providers.openai.url' => $creds['base_url']]);
            }

            return $callback($creds['lab'], $creds['model']);
        } finally {
            // Always restore — prevents config leakage into subsequent requests/jobs
            config([$configKey => $originalKey]);
            config(['ai.providers.openai.url' => $originalUrl]);
        }
    }

    /**
     * Check whether a workspace-level AI feature flag is enabled.
     */
    public function isFeatureEnabled(int $workspaceId, string $feature): bool
    {
        $settings = Cache::remember(
            "workspace.ai_settings.{$workspaceId}",
            now()->addMinutes(5),
            fn () => Workspace::findOrFail($workspaceId)->settings ?? [],
        );

        return (bool) (($settings['ai_features'] ?? [])[$feature] ?? config("ai.features.{$feature}", true));
    }

    /**
     * Return sanitised AI config for the frontend (API key is never exposed).
     */
    public function getForWorkspace(int $workspaceId): array
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $settings = $workspace->settings ?? [];

        return [
            'provider' => $settings['ai_provider'] ?? 'anthropic',
            'model' => $settings['ai_model'] ?? null,
            'base_url' => $settings['ai_base_url'] ?? null,
            'key_set' => ! empty($settings['ai_api_key']),
            'features' => $settings['ai_features'] ?? [
                'reply_suggestions' => true,
                'auto_categorization' => true,
                'summarization' => true,
            ],
            'rag' => $settings['ai_rag'] ?? [
                'top_k' => 5,
                'min_score' => 0.7,
            ],
        ];
    }

    /**
     * Validate and persist AI settings for a workspace.
     * Passing an empty api_key keeps the existing encrypted key unchanged.
     */
    public function saveForWorkspace(int $workspaceId, array $validated): void
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $settings = $workspace->settings ?? [];

        $settings['ai_provider'] = $validated['provider'];
        $settings['ai_model'] = $validated['model'] ?? null;

        if ($validated['provider'] === 'openai-compatible') {
            $settings['ai_base_url'] = $validated['base_url'] ?? null;
        } else {
            unset($settings['ai_base_url']);
        }

        if (! empty($validated['api_key'])) {
            $settings['ai_api_key'] = Crypt::encryptString($validated['api_key']);
        }

        $settings['ai_features'] = [
            'reply_suggestions' => (bool) ($validated['feature_reply_suggestions'] ?? true),
            'auto_categorization' => (bool) ($validated['feature_auto_categorization'] ?? true),
            'summarization' => (bool) ($validated['feature_summarization'] ?? true),
        ];

        $settings['ai_rag'] = [
            'top_k' => (int) ($validated['rag_top_k'] ?? 5),
            'min_score' => (float) ($validated['rag_min_score'] ?? 0.7),
        ];

        $workspace->settings = $settings;
        $workspace->save();

        Cache::forget("workspace.ai_settings.{$workspaceId}");
    }
}
