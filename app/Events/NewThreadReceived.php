<?php

namespace App\Events;

use App\Domains\Conversation\Models\Thread;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewThreadReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Thread $thread,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("conversation.{$this->thread->conversation_id}"),
        ];

        // Also broadcast on the public livechat channel so visitor widgets can receive
        // agent replies without needing WebSocket authentication.
        if ($this->thread->source === 'chat') {
            $channels[] = new Channel("livechat.{$this->thread->conversation_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'thread.created';
    }

    public function broadcastWith(): array
    {
        return [
            'thread' => [
                'id'              => $this->thread->id,
                'conversation_id' => $this->thread->conversation_id,
                'user_id'         => $this->thread->user_id,
                'customer_id'     => $this->thread->customer_id,
                'type'            => $this->thread->type,
                'body'            => $this->thread->body,
                'source'          => $this->thread->source,
                'created_at'      => $this->thread->created_at,
                'user'            => $this->thread->user?->only(['id', 'name', 'avatar']),
                'customer'        => $this->thread->customer?->only(['id', 'name', 'avatar']),
            ],
        ];
    }
}
