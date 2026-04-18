<?php

namespace App\Services;

use App\Domains\Channel\Jobs\HandleLiveChatMessageJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use Illuminate\Support\Collection;

class LiveChatService
{
    /**
     * Resolve (or create) the customer and their open chat conversation,
     * then dispatch the message job. Returns the conversation ID so the
     * widget can subscribe to the real-time channel immediately.
     *
     * @return array{status: string, conversation_id: int}
     */
    public function store(array $validated): array
    {
        $email = ! empty($validated['visitor_email'])
            ? $validated['visitor_email']
            : "visitor_{$validated['visitor_id']}@livechat.local";

        $customer = Customer::firstOrCreate(
            ['workspace_id' => $validated['workspace_id'], 'email' => $email],
            ['name' => $validated['visitor_name'] ?? 'Visitor'],
        );

        $conversation = Conversation::where('workspace_id', $validated['workspace_id'])
            ->where('customer_id', $customer->id)
            ->where('channel_type', 'chat')
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'workspace_id' => $validated['workspace_id'],
                'customer_id' => $customer->id,
                'subject' => 'Live chat with '.($validated['visitor_name'] ?? 'Visitor'),
                'status' => 'open',
                'channel_type' => 'chat',
                'channel_id' => $validated['visitor_id'],
                'last_reply_at' => now(),
            ]);
        }

        HandleLiveChatMessageJob::dispatch(
            $conversation,
            $customer,
            $validated['message'],
        )->onQueue('default');

        return [
            'status' => 'sent',
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Fetch message threads for a visitor's conversation.
     */
    public function messages(string $visitorId, int $conversationId): Collection
    {
        $email = "visitor_{$visitorId}@livechat.local";
        $customer = Customer::where('email', $email)->first();

        if (! $customer) {
            return collect();
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('customer_id', $customer->id)
            ->where('channel_type', 'chat')
            ->first();

        if (! $conversation) {
            return collect();
        }

        return $conversation->threads()
            ->with(['user:id,name,avatar', 'customer:id,name'])
            ->where('type', 'message')
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'customer_id', 'body', 'created_at']);
    }
}
