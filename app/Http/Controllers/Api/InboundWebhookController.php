<?php

namespace App\Http\Controllers\Api;

use App\Domains\Channel\Jobs\ProcessWebhookMessageJob;
use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundWebhookController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        // Reject oversized payloads — check actual body length, not the client-supplied
        // Content-Length header (which can be spoofed or omitted to bypass a header-only check).
        if (strlen($request->getContent()) > 1_048_576) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }

        $mailbox = Mailbox::where('webhook_token', $token)->where('active', true)->firstOrFail();
        ProcessWebhookMessageJob::dispatch($mailbox->id, $request->all())->onQueue('webhooks');

        return response()->json(['status' => 'queued']);
    }
}
