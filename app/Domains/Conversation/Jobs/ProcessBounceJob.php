<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Conversation\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Records an email bounce as an activity note on the conversation
 * and moves hard-bounce conversations to pending for agent review.
 */
class ProcessBounceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int     $mailboxId,
        public readonly string  $toEmail,
        public readonly string  $bounceType,       // 'hard' | 'soft'
        public readonly string  $bounceMessage,
        public readonly ?string $originalMessageId = null,
    ) {
        $this->onQueue('email-inbound');
    }

    public function handle(): void
    {
        $conversation = null;

        if ($this->originalMessageId) {
            $thread       = Thread::whereJsonContains('meta->message_id', $this->originalMessageId)->first();
            $conversation = $thread?->conversation;
        }

        if (! $conversation) {
            Log::warning('ProcessBounceJob: no conversation found', [
                'mailbox_id'  => $this->mailboxId,
                'to_email'    => $this->toEmail,
                'bounce_type' => $this->bounceType,
            ]);
            return;
        }

        $conversation->threads()->create([
            'type'       => 'activity',
            'body'       => "<p>⚠️ Email bounced ({$this->bounceType}): {$this->bounceMessage}</p>",
            'body_plain' => "Email bounced ({$this->bounceType}): {$this->bounceMessage}",
            'source'     => 'email',
            'status'     => 'note',
            'meta'       => [
                'bounce_type'    => $this->bounceType,
                'bounce_to'      => $this->toEmail,
                'bounce_message' => $this->bounceMessage,
            ],
        ]);

        if ($this->bounceType === 'hard') {
            $conversation->update(['status' => 'pending']);
        }

        Log::info('ProcessBounceJob: bounce recorded', [
            'conversation_id' => $conversation->id,
            'bounce_type'     => $this->bounceType,
        ]);
    }
}
