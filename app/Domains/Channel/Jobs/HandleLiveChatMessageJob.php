<?php

namespace App\Domains\Channel\Jobs;

use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Enums\ChannelType;
use App\Events\ConversationUpdated;
use App\Events\NewThreadReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleLiveChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Customer $customer,
        public readonly string $message,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        /** @var Thread $thread */
        $thread = $this->conversation->threads()->create([
            'customer_id' => $this->customer->id,
            'type' => 'message',
            'body' => e($this->message),
            'body_plain' => $this->message,
            'source' => 'chat',
        ]);

        $this->conversation->update(['last_reply_at' => now()]);

        broadcast(new NewThreadReceived($thread));
        broadcast(new ConversationUpdated($this->conversation->fresh()));

        // Skip AI reply suggestions for live chat — agent is present in real-time
        if (config('ai.features.reply_suggestions', true) && $this->conversation->channel_type !== ChannelType::Chat) {
            GenerateReplySuggestionJob::dispatch($this->conversation)->onQueue('ai');
        }
    }
}
