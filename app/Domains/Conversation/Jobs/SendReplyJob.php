<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Channel\Drivers\WhatsAppDriver;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ChannelType;
use App\Events\NewThreadReceived;
use App\Services\DynamicMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 168;

    public int $timeout = 120;

    public function __construct(
        public readonly Thread $thread,
        public readonly Conversation $conversation,
    ) {}

    public function handle(): void
    {
        $conversation = $this->conversation->load(['mailbox', 'customer', 'threads']);
        $this->thread->loadMissing(['user', 'attachments']);
        $mailbox = $conversation->mailbox;
        $customer = $conversation->customer;

        // Route to correct channel driver
        if ($conversation->channel_type === ChannelType::WhatsApp) {
            app(WhatsAppDriver::class)->send($this->thread);
            broadcast(new NewThreadReceived($this->thread));

            return;
        }

        if (! $customer?->email) {
            return;
        }

        // Build per-mailbox mailer config dynamically
        $smtp = $mailbox->smtp_config;

        // In local env with no SMTP config, fall back to the default mailer (log driver)
        if (! $smtp && app()->environment('local')) {
            broadcast(new NewThreadReceived($this->thread));

            return;
        }

        $mailer = $smtp ? app(DynamicMailerService::class)->fromSmtpConfig($smtp) : Mail::mailer('smtp');

        $thread = $this->thread;

        $agentSignature = $this->thread->user?->signature;

        // Collect CC recipients from the most recent inbound customer thread
        $ccRecipients = $this->resolveCcRecipients($conversation);

        // Generate a stable per-thread message ID for bounce tracking.
        // Stored without angle brackets; Symfony IdentificationHeader adds them when serialising.
        $outboundMsgId = 'thread-'.$thread->id.'-'.Str::uuid().'@fusterai';

        // Store with angle brackets so bounce lookups use the same format mail servers return.
        // Merge into the existing meta array rather than using JSON path syntax, which
        // fails on PostgreSQL when the column is initially NULL.
        $thread->update(['meta' => array_merge($thread->meta ?? [], [
            'outbound_message_id' => "<{$outboundMsgId}>",
        ])]);

        $unsubscribeUrl = URL::signedRoute('unsubscribe', ['customer' => $customer->id]);

        $mailer->send([], [], function (Message $msg) use ($conversation, $mailbox, $customer, $thread, $agentSignature, $ccRecipients, $outboundMsgId, $unsubscribeUrl) {
            $msg->to($customer->email, $customer->name)
                ->from($mailbox->email, $mailbox->name)
                ->subject($this->buildSubject($conversation))
                ->html($this->buildHtmlBody($thread, $mailbox, $agentSignature))
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
                if (file_exists(storage_path('app/'.$attachment->path))) {
                    $msg->attach(
                        storage_path('app/'.$attachment->path),
                        ['as' => $attachment->filename, 'mime' => $attachment->mime_type],
                    );
                }
            }
        });

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

    private function buildHtmlBody(Thread $thread, $mailbox, ?string $agentSignature = null): string
    {
        $body = $thread->body;

        // Agent signature takes priority; fall back to mailbox signature
        $rawSignature = $agentSignature ?? $mailbox->signature ?? null;
        $signature = $rawSignature
            ? '<br><br>--<br>'.nl2br(e($rawSignature))
            : '';

        return $body.$signature;
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
