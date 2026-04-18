<?php

namespace App\Http\Controllers\Api;

use App\Domains\Conversation\Jobs\ProcessBounceJob;
use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives email bounce notifications from transactional providers.
 * Normalises Postmark / Mailgun / AWS SES SNS payloads into a common format
 * and dispatches ProcessBounceJob for async handling.
 *
 * Endpoint: POST /api/webhooks/bounce/{token}
 * Auth: webhook_token in URL (same pattern as inbound webhooks)
 */
class BounceWebhookController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        $mailbox = Mailbox::where('webhook_token', $token)->where('active', true)->firstOrFail();
        $payload = $request->all();

        ProcessBounceJob::dispatch(
            $mailbox->id,
            $this->extractEmail($payload),
            $this->extractBounceType($payload),
            $this->extractMessage($payload),
            $this->extractMessageId($payload),
        );

        return response()->json(['status' => 'queued']);
    }

    private function extractEmail(array $p): string
    {
        $email = $p['Recipient']                        // Postmark
            ?? $p['recipient']
            ?? ($p['event-data']['recipient'] ?? null)  // Mailgun
            ?? ($p['mail']['destination'][0] ?? null);  // SES

        if ($email === null) {
            Log::warning('BounceWebhookController: could not extract recipient email from payload', [
                'payload_keys' => array_keys($p),
            ]);

            return 'unknown@bounce.local';
        }

        return $email;
    }

    private function extractBounceType(array $p): string
    {
        $type = strtolower(
            $p['Type']                                    // Postmark: HardBounce/SoftBounce
            ?? $p['event']                                // Mailgun: failed/bounced
            ?? ($p['bounce']['bounceType'] ?? '')         // SES
        );

        return (str_contains($type, 'hard') || $type === 'failed') ? 'hard' : 'soft';
    }

    private function extractMessage(array $p): string
    {
        return $p['Description']
            ?? $p['error']
            ?? ($p['bounce']['bouncedRecipients'][0]['diagnosticCode'] ?? null)
            ?? 'Delivery failed';
    }

    private function extractMessageId(array $p): ?string
    {
        return $p['MessageID']
            ?? $p['Message-Id']
            ?? ($p['mail']['messageId'] ?? null)
            ?? null;
    }
}
