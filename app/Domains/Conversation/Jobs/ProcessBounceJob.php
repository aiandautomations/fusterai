<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Conversation\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Enums\ThreadType;

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
        [$conversation, $bouncedThread] = $this->resolveConversation();

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
            'meta'       => [
                'bounce_type'    => $this->bounceType,
                'bounce_to'      => $this->toEmail,
                'bounce_message' => $this->bounceMessage,
            ],
        ]);

        if ($this->bounceType === 'hard') {
            // Hard bounce — move to pending so an agent reviews delivery failure
            $conversation->update(['status' => 'pending']);
        } elseif ($this->bounceType === 'soft' && $bouncedThread) {
            // Soft bounce — temporary failure; retry the send after 30 minutes
            SendReplyJob::dispatch($bouncedThread, $conversation)
                ->onQueue('email-outbound')
                ->delay(now()->addMinutes(30));
        }

        Log::info('ProcessBounceJob: bounce recorded', [
            'conversation_id' => $conversation->id,
            'bounce_type'     => $this->bounceType,
        ]);
    }

    /**
     * Resolve the conversation from the bounced message ID.
     *
     * Tries two strategies:
     * 1. Match by outbound_message_id stored in thread meta (precise, for our own emails)
     * 2. Match by inbound message_id stored in thread meta (legacy / fallback)
     *
     * @return array{0: \App\Domains\Conversation\Models\Conversation|null, 1: Thread|null}
     */
    private function resolveConversation(): array
    {
        if (!$this->originalMessageId) {
            return [null, null];
        }

        // Strategy 1: outbound thread (agent reply that bounced)
        $outboundThread = Thread::where('type', ThreadType::Message)
            ->whereJsonContains('meta->outbound_message_id', $this->originalMessageId)
            ->with('conversation')
            ->first();

        if ($outboundThread?->conversation) {
            return [$outboundThread->conversation, $outboundThread];
        }

        // Strategy 2: inbound thread message_id (older behaviour / inbound emails)
        $inboundThread = Thread::whereJsonContains('meta->message_id', $this->originalMessageId)
            ->with('conversation')
            ->first();

        return [$inboundThread?->conversation, null];
    }
}
