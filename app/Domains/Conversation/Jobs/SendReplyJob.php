<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Channel\Drivers\WhatsAppDriver;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ChannelType;
use App\Events\NewThreadReceived;
use App\Services\DynamicMailerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 120;

    /** Exponential backoff: 1m, 5m, 15m, 1h, then hourly up to the retryUntil ceiling. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600, 3600, 3600, 3600, 3600, 3600];
    }

    public function __construct(
        public readonly Thread $thread,
        public readonly Conversation $conversation,
        public readonly ?Carbon $scheduledAt = null,
    ) {}

    public function handle(): void
    {
        // Re-fetch thread; if it was deleted bail silently
        $thread = $this->thread->fresh();
        if ($thread === null) {
            return;
        }

        // Scheduled send was cancelled by the agent (send_at cleared before job ran)
        if ($this->scheduledAt !== null && $thread->send_at === null) {
            return;
        }

        $thread->loadMissing(['user', 'attachments']);
        $conversation = $this->conversation->load(['mailbox', 'customer', 'threads']);
        $mailbox = $conversation->mailbox;
        $customer = $conversation->customer;

        // Route to correct channel driver
        if ($conversation->channel_type === ChannelType::WhatsApp) {
            app(WhatsAppDriver::class)->send($thread);
            broadcast(new NewThreadReceived($thread));

            return;
        }

        if (! $customer?->email) {
            return;
        }

        // No mailbox means this is a portal/non-email conversation — nothing to send
        if (! $mailbox) {
            return;
        }

        // Build per-mailbox mailer config dynamically
        $smtp = $mailbox->smtp_config;

        // In local env with no SMTP config, fall back to the default mailer (log driver)
        if (! $smtp && app()->environment('local')) {
            broadcast(new NewThreadReceived($thread));

            return;
        }

        $mailer = $smtp ? app(DynamicMailerService::class)->fromSmtpConfig($smtp) : Mail::mailer('smtp');

        $agentSignature = $thread->user?->signature;

        // Collect CC recipients from the most recent inbound customer thread
        $ccRecipients = $this->resolveCcRecipients($conversation);

        // Generate a stable per-thread message ID for bounce tracking.
        // Stored without angle brackets; Symfony IdentificationHeader adds them when serialising.
        $outboundMsgId = 'thread-'.$thread->id.'-'.Str::uuid().'@fusterai';

        $trackingToken = Str::random(32);
        $unsubscribeUrl = URL::signedRoute('unsubscribe', ['customer' => $customer->id]);

        // Single update: merge meta (outbound_message_id) + tracking_token together.
        // Two separate updates would be two round-trips; merging is one.
        $thread->update([
            'tracking_token' => $trackingToken,
            'meta' => array_merge($thread->meta ?? [], [
                'outbound_message_id' => "<{$outboundMsgId}>",
            ]),
        ]);

        $mailer->send([], [], function (Message $msg) use ($conversation, $mailbox, $customer, $thread, $agentSignature, $ccRecipients, $outboundMsgId, $unsubscribeUrl, $trackingToken) {
            $msg->to($customer->email, $customer->name)
                ->from($mailbox->email, $mailbox->name)
                ->subject($this->buildSubject($conversation))
                ->html($this->buildHtmlBody($thread, $mailbox, $agentSignature, $trackingToken))
                ->text(strip_tags($thread->body));

            foreach ($ccRecipients as $cc) {
                $msg->cc($cc['email'], $cc['name'] ?? null);
            }

            // Set In-Reply-To header to thread the email
            $msgId = $this->conversationMessageId($conversation);
            // Message-ID requires Symfony's IdentificationHeader (not plain text)
            $msg->getHeaders()->addIdHeader('Message-ID', $outboundMsgId);
            $msg->getHeaders()->addTextHeader('In-Reply-To', $msgId);
            $msg->getHeaders()->addTextHeader('References', $msgId);
            $msg->getHeaders()->addTextHeader('X-FusterAI-Conversation', (string) $conversation->id);
            $msg->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>, <mailto:{$mailbox->email}?subject=Unsubscribe>");
            $msg->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

            // Attach files
            foreach ($thread->attachments as $attachment) {
                $disk = Storage::disk(config('filesystems.default'));
                if ($disk->exists($attachment->path)) {
                    $msg->attachData(
                        $disk->get($attachment->path),
                        $attachment->filename,
                        ['mime' => $attachment->mime_type],
                    );
                }
            }
        });

        // Clear send_at now that the scheduled message has been sent
        if ($thread->send_at !== null) {
            $thread->update(['send_at' => null]);
        }

        // Broadcast to frontend after send
        broadcast(new NewThreadReceived($thread));
    }

    public function retryUntil(): \DateTime
    {
        return now()->addWeek();
    }

    private function buildSubject(Conversation $conversation): string
    {
        return Str::startsWith($conversation->subject, 'Re:')
            ? $conversation->subject
            : 'Re: '.$conversation->subject;
    }

    private function buildHtmlBody(Thread $thread, $mailbox, ?string $agentSignature = null, ?string $trackingToken = null): string
    {
        $body = $thread->body;

        // Agent signature takes priority; fall back to mailbox signature
        $rawSignature = $agentSignature ?? $mailbox->signature ?? null;
        $signature = $rawSignature
            ? '<br><br>--<br>'.nl2br(e($rawSignature))
            : '';

        $pixel = $trackingToken
            ? '<img src="'.url('/t/'.$trackingToken.'.gif').'" width="1" height="1" style="display:none" alt="">'
            : '';

        return $body.$signature.$pixel;
    }

    private function conversationMessageId(Conversation $conversation): string
    {
        return '<conversation-'.$conversation->id.'@fusterai>';
    }

    /**
     * Pull CC recipients from the last inbound customer thread's meta.
     * This lets agents reply-all to threads that had CC recipients.
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function resolveCcRecipients(Conversation $conversation): array
    {
        $lastCustomerThread = $conversation->threads
            ->whereNotNull('customer_id')
            ->sortByDesc('created_at')
            ->first();

        return $lastCustomerThread?->meta['cc'] ?? [];
    }
}
