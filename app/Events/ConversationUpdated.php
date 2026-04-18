<?php

namespace App\Events;

use App\Domains\Conversation\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->conversation->workspace_id}"),
            new PrivateChannel("conversation.{$this->conversation->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'status' => $this->conversation->status,
            'priority' => $this->conversation->priority,
            'assigned_user_id' => $this->conversation->assigned_user_id,
            'last_reply_at' => $this->conversation->last_reply_at,
            'ai_summary' => $this->conversation->ai_summary,
            'ai_tags' => $this->conversation->ai_tags,
        ];
    }
}
