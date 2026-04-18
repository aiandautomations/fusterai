<?php

namespace App\Http\Controllers\Mailboxes;

use App\Domains\Mailbox\Models\Channel;
use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailboxes\UpdateWhatsAppRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhatsAppMailboxController extends Controller
{
    public function show(Request $request, Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        /** @var Channel|null $channel */
        $channel = $mailbox->channels()->where('type', 'whatsapp')->first();
        $decryptedConfig = $channel?->config; // auto-decrypted via model accessor

        return Inertia::render('Mailboxes/WhatsAppSetup', [
            'mailbox' => [
                'id' => $mailbox->id,
                'name' => $mailbox->name,
                'webhook_token' => $mailbox->webhook_token,
                'channel' => $decryptedConfig ? ['config' => $decryptedConfig] : null,
            ],
            'webhookUrl' => url('/api/webhooks/whatsapp/'.$mailbox->webhook_token),
        ]);
    }

    public function update(UpdateWhatsAppRequest $request, Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        $mailbox->channels()->updateOrCreate(
            ['type' => 'whatsapp'],
            ['config' => $request->validated(), 'active' => true],
        );

        return back()->with('success', 'WhatsApp credentials saved.');
    }
}
