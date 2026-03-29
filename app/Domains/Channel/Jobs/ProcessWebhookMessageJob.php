<?php

namespace App\Domains\Channel\Jobs;

use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\ConversationUpdated;
use App\Events\NewThreadReceived;
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

        // Find or create customer by email
        $customer = Customer::firstOrCreate(
            ['workspace_id' => $mailbox->workspace_id, 'email' => strtolower($fromEmail)],
            ['name' => $fromName ?: explode('@', $fromEmail)[0]],
        );

        // Create new conversation
        $conversation = Conversation::create([
            'workspace_id'  => $mailbox->workspace_id,
            'mailbox_id'    => $mailbox->id,
            'customer_id'   => $customer->id,
            'subject'       => $subject,
            'status'        => 'open',
            'channel_type'  => 'api',
            'last_reply_at' => now(),
        ]);

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
                'payload'    => array_diff_key($payload, array_flip(['from_name', 'from_email', 'subject', 'body'])),
            ],
        ]);

        // Fire module hooks
        Hooks::doAction('conversation.created', $conversation);
        Hooks::doAction('thread.created', $thread);

        // Broadcast real-time update
        broadcast(new NewThreadReceived($thread));
        broadcast(new ConversationUpdated($conversation->fresh()));

        // Dispatch AI jobs
        if (config('ai.features.reply_suggestions', true)) {
            GenerateReplySuggestionJob::dispatch($conversation)->onQueue('ai');
        }
        if (config('ai.features.auto_categorization', true)) {
            CategorizeConversationJob::dispatch($conversation)->onQueue('ai');
        }
    }
}
