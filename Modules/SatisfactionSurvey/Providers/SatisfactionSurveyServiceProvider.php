<?php

namespace Modules\SatisfactionSurvey\Providers;

use App\Domains\Conversation\Models\Conversation;
use App\Support\Hooks;
use Illuminate\Support\ServiceProvider;
use Modules\SatisfactionSurvey\Jobs\SendSurveyJob;
use Modules\SatisfactionSurvey\Models\SurveyResponse;

class SatisfactionSurveyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ── Views ─────────────────────────────────────────────────────────────
        $this->loadViewsFrom(
            __DIR__.'/../Resources/views',
            'satisfaction-survey'
        );

        // ── Migrations ────────────────────────────────────────────────────────
        $this->loadMigrationsFrom(
            __DIR__.'/../Database/Migrations'
        );

        // ── Action: send survey when conversation is closed ───────────────────
        Hooks::addAction('conversation.closed', function (Conversation $conversation) {
            // Small delay so the agent can finish any final notes before the
            // customer receives the survey email.
            SendSurveyJob::dispatch($conversation)
                ->onQueue('email-outbound')
                ->delay(now()->addMinutes(5));
        });

        // ── Filter: append survey data to the conversation show payload ───────
        Hooks::addFilter('conversation.show.extra', function (array $extra, Conversation $conversation) {
            $response = SurveyResponse::where('conversation_id', $conversation->id)->first();

            $extra['survey'] = $response ? [
                'rating' => $response->rating,
                'responded_at' => $response->responded_at->toIso8601String(),
            ] : null;

            return $extra;
        });

        // ── Filter: note AI about survey ──────────────────────────────────────
        Hooks::addFilter('ai.system_prompt', function (string $prompt) {
            return $prompt."\n\nNote: A satisfaction survey (👍/👎) will be emailed to the customer automatically when this conversation is closed.";
        });
    }
}
