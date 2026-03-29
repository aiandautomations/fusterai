<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Collision detection — fires when agent opens/leaves a conversation.
 */
class AgentViewing implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $conversationId,
        public readonly int    $userId,
        public readonly string $userName,
        public readonly string $action, // 'joined' | 'left'
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("conversation.{$this->conversationId}.presence");
    }

    public function broadcastAs(): string
    {
        return 'agent.' . $this->action;
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'   => $this->userId,
            'user_name' => $this->userName,
        ];
    }
}
