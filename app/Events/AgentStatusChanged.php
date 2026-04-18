<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int $userId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("workspace.{$this->workspaceId}");
    }

    public function broadcastAs(): string
    {
        return 'agent.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'status' => $this->status,
        ];
    }
}
