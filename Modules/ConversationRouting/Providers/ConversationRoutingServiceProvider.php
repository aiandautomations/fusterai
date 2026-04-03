<?php

namespace Modules\ConversationRouting\Providers;

use App\Domains\Conversation\Models\Conversation;
use App\Events\ConversationUpdated;
use App\Support\Hooks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\ConversationRouting\Models\RoutingConfig;

class ConversationRoutingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Hooks::addAction('conversation.created', function (Conversation $conversation) {
            // Only route unassigned conversations
            if ($conversation->assigned_user_id !== null) {
                return;
            }

            $config = RoutingConfig::forConversation($conversation);

            if (! $config) {
                return;
            }

            $agent = match ($config->mode) {
                'least_loaded' => $config->leastLoadedAgent(),
                default        => $config->nextRoundRobinAgent(),
            };

            if (! $agent) {
                Log::info('ConversationRouting: no eligible agents for conversation', [
                    'conversation_id' => $conversation->id,
                    'config_id'       => $config->id,
                ]);

                return;
            }

            $conversation->update(['assigned_user_id' => $agent->id]);

            broadcast(new ConversationUpdated($conversation->fresh()));

            Log::info('ConversationRouting: assigned conversation', [
                'conversation_id' => $conversation->id,
                'user_id'         => $agent->id,
                'mode'            => $config->mode,
            ]);
        });
    }
}
