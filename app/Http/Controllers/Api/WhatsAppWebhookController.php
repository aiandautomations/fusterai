<?php

namespace App\Http\Controllers\Api;

use App\Domains\Channel\Jobs\ProcessWhatsAppWebhookJob;
use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle GET verification challenge from Meta.
     */
    public function verify(Request $request, string $token): Response
    {
        $mailbox = Mailbox::where('webhook_token', $token)
            ->where('channel_type', 'whatsapp')
            ->firstOrFail();

        $mode        = $request->query('hub_mode');
        $challenge   = $request->query('hub_challenge');
        $verifyToken = $request->query('hub_verify_token');

        if ($mode === 'subscribe' && $verifyToken === $token) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403, 'Verification failed.');
    }

    /**
     * Receive inbound WhatsApp messages from Meta.
     */
    public function receive(Request $request, string $token): JsonResponse
    {
        $mailbox = Mailbox::where('webhook_token', $token)
            ->where('channel_type', 'whatsapp')
            ->first();

        if (!$mailbox) {
            return response()->json(['error' => 'Invalid token'], 403);
        }

        // Validate Meta's HMAC-SHA256 signature
        /** @var \App\Domains\Mailbox\Models\Channel|null $channel */
        $channel   = $mailbox->channels()->where('type', 'whatsapp')->first();
        $appSecret = $channel?->config['app_secret'] ?? null;
        if ($appSecret) {
            $signature = $request->header('X-Hub-Signature-256', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if (!hash_equals($expected, $signature)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        ProcessWhatsAppWebhookJob::dispatch($mailbox, $request->all())->onQueue('webhooks');

        return response()->json(['status' => 'ok']);
    }
}
