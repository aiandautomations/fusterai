<?php

namespace App\Domains\Channel\Drivers;

use App\Domains\Channel\Contracts\ChannelDriver;
use App\Domains\Conversation\Models\Thread;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Broadcast;

class LiveChatDriver implements ChannelDriver
{
    public function send(Thread $thread): void
    {
        // Broadcast the standard event on the livechat-specific channel
        Broadcast::on(new Channel("livechat.{$thread->conversation_id}"))
            ->as('thread.created')
            ->with([
                'thread' => [
                    'id'              => $thread->id,
                    'conversation_id' => $thread->conversation_id,
                    'type'            => $thread->type,
                    'body'            => $thread->body,
                    'source'          => $thread->source,
                    'created_at'      => $thread->created_at,
                    'user'            => $thread->user?->only(['id', 'name', 'avatar']),
                    'customer'        => $thread->customer?->only(['id', 'name', 'avatar']),
                ],
            ])
            ->sendNow();
    }
}
