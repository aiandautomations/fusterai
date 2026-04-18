<?php

namespace App\Domains\Channel\Drivers;

use App\Domains\Conversation\Models\Thread;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppDriver
{
    public function send(Thread $thread): void
    {
        $conversation = $thread->conversation()->with(['mailbox.channels', 'customer'])->first();
        $channel = $conversation->mailbox->channels()->where('type', 'whatsapp')->first();

        if (! $channel) {
            Log::warning('WhatsApp channel config missing for mailbox '.$conversation->mailbox_id);

            return;
        }

        $config = $channel->config ?? [];
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $accessToken = $config['access_token'] ?? null;
        $phone = $conversation->customer?->phone;

        if (! $phoneNumberId || ! $accessToken || ! $phone) {
            Log::warning('WhatsApp send skipped — missing config or customer phone', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        $body = strip_tags($thread->body_plain ?: $thread->body);

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v17.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp send failed', [
                'conversation_id' => $conversation->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
