<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiSuggestionReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $conversationId,
        public readonly string $content,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string
    {
        return 'ai.suggestion.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'content'         => $this->content,
        ];
    }
}
