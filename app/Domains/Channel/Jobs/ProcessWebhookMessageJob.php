<?php

namespace App\Domains\Channel\Jobs;

use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\ConversationUpdated;
use App\Events\NewThreadReceived;
use App\Services\AiSettingsService;
use App\Support\Hooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int   $mailboxId,
        public readonly array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $mailbox = Mailbox::find($this->mailboxId);
        if (!$mailbox) {
            return;
        }

        $payload = $this->payload;

        // Extract fields with sensible defaults
        $fromName  = $payload['from_name']  ?? $payload['name']  ?? 'Unknown';
        $fromEmail = $payload['from_email'] ?? $payload['email'] ?? 'noreply@webhook.local';
        $subject   = $payload['subject']    ?? $payload['title'] ?? '(No Subject)';
        $body      = $payload['body']       ?? $payload['message'] ?? $payload['content'] ?? '';

        // Find or create customer by email — consistent with email and API channels
        $customer = Customer::resolveOrCreate($mailbox->workspace_id, $fromEmail, $fromName);

        // Thread into an existing conversation when conversation_id is provided.
        // This lets integrations append follow-up messages to an open ticket
        // rather than always opening a new one.
        $isNew        = false;
        $conversation = null;

        if (!empty($payload['conversation_id'])) {
            $conversation = Conversation::where('workspace_id', $mailbox->workspace_id)
                ->where('mailbox_id', $mailbox->id)
                ->find((int) $payload['conversation_id']);
        }

        if (!$conversation) {
            $isNew        = true;
            $conversation = Conversation::create([
                'workspace_id'  => $mailbox->workspace_id,
                'mailbox_id'    => $mailbox->id,
                'customer_id'   => $customer->id,
                'subject'       => $subject,
                'status'        => 'open',
                'channel_type'  => 'api',
                'last_reply_at' => now(),
            ]);
        }

        // Create thread
        $thread = $conversation->threads()->create([
            'customer_id' => $customer->id,
            'type'        => 'message',
            'body'        => nl2br(e($body)),
            'body_plain'  => $body,
            'source'      => 'api',
            'meta'        => [
                'from_email' => $fromEmail,
                'from_name'  => $fromName,
                'payload'    => array_diff_key($payload, array_flip(['from_name', 'from_email', 'subject', 'body', 'conversation_id'])),
            ],
        ]);

        $conversation->update(['status' => 'open', 'last_reply_at' => now()]);

        // Fire module hooks
        if ($isNew) {
            Hooks::doAction('conversation.created', $conversation);
        }
        Hooks::doAction('thread.created', $thread);

        // Broadcast real-time update
        broadcast(new NewThreadReceived($thread));
        broadcast(new ConversationUpdated($conversation->fresh()));

        // Dispatch AI jobs — read workspace-level feature flags via AiSettingsService
        // so behaviour is consistent with email and other inbound channels.
        $ai = app(AiSettingsService::class);
        if ($ai->isFeatureEnabled($mailbox->workspace_id, 'reply_suggestions')) {
            GenerateReplySuggestionJob::dispatch($conversation)->onQueue('ai');
        }
        if ($ai->isFeatureEnabled($mailbox->workspace_id, 'auto_categorization') && $isNew) {
            CategorizeConversationJob::dispatch($conversation)->onQueue('ai');
        }
    }
}
