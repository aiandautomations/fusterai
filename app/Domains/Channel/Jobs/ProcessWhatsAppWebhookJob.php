<?php

namespace App\Domains\Channel\Jobs;

use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\NewThreadReceived;
use App\Services\AiSettingsService;
use App\Support\Hooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly Mailbox $mailbox,
        public readonly array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        try {
            $entry = $this->payload['entry'][0] ?? null;
            $changes = $entry['changes'][0] ?? null;
            $value = $changes['value'] ?? null;
            $message = $value['messages'][0] ?? null;

            if (! $message) {
                Log::info('ProcessWhatsAppWebhookJob: no message in payload, skipping.', [
                    'mailbox_id' => $this->mailbox->id,
                ]);

                return;
            }

            $from = $message['from'] ?? null;
            $messageId = $message['id'] ?? null;
            $body = $message['text']['body'] ?? ('['.($message['type'] ?? 'message').']');
            $displayName = $value['contacts'][0]['profile']['name'] ?? $from;

            if (! $from || ! $messageId) {
                return;
            }

            // Deduplicate — Meta retries webhooks on failure; skip if already processed
            if (Thread::whereJsonContains('meta->whatsapp_message_id', $messageId)->exists()) {
                Log::info('ProcessWhatsAppWebhookJob: duplicate message, skipping.', ['message_id' => $messageId]);

                return;
            }

            // Find or create customer by phone
            $customer = Customer::resolveOrCreateByPhone(
                $this->mailbox->workspace_id,
                $from,
                $displayName ?? $from,
            );

            // Find open conversation for this customer+mailbox or create one
            $conversation = Conversation::where('workspace_id', $this->mailbox->workspace_id)
                ->where('mailbox_id', $this->mailbox->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'open')
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'workspace_id' => $this->mailbox->workspace_id,
                    'mailbox_id' => $this->mailbox->id,
                    'customer_id' => $customer->id,
                    'subject' => 'WhatsApp: '.Str::limit($body, 60),
                    'status' => 'open',
                    'channel_type' => 'whatsapp',
                    'last_reply_at' => now(),
                ]);

                Hooks::doAction('conversation.created', $conversation);
            }

            // Create the thread
            $thread = Thread::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'type' => 'message',
                'source' => 'whatsapp',
                'body' => e($body),
                'body_plain' => $body,
                'meta' => ['whatsapp_message_id' => $messageId],
            ]);

            $conversation->update(['last_reply_at' => now()]);

            Hooks::doAction('thread.created', $thread);

            broadcast(new NewThreadReceived($thread));

            // Trigger AI jobs — respect workspace feature flags
            $ai = app(AiSettingsService::class);
            if ($ai->isFeatureEnabled($this->mailbox->workspace_id, 'reply_suggestions')) {
                GenerateReplySuggestionJob::dispatch($conversation)->onQueue('ai');
            }
            if ($ai->isFeatureEnabled($this->mailbox->workspace_id, 'auto_categorization') && $conversation->wasRecentlyCreated) {
                CategorizeConversationJob::dispatch($conversation)->onQueue('ai');
            }
        } catch (\Throwable $e) {
            Log::error('ProcessWhatsAppWebhookJob failed', [
                'mailbox_id' => $this->mailbox->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
