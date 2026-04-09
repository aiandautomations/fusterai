<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ChannelType;
use App\Events\NewThreadReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Services\DynamicMailerService;
use Illuminate\Support\Str;

class SendReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 168;
    public int $timeout = 120;

    public function __construct(
        public readonly Thread $thread,
        public readonly Conversation $conversation,
    ) {}

    public function handle(): void
    {
        $conversation = $this->conversation->load(['mailbox', 'customer', 'threads']);
        $this->thread->loadMissing(['user', 'attachments']);
        $mailbox      = $conversation->mailbox;
        $customer     = $conversation->customer;

        // Route to correct channel driver
        if ($conversation->channel_type === ChannelType::WhatsApp) {
            app(\App\Domains\Channel\Drivers\WhatsAppDriver::class)->send($this->thread);
            broadcast(new NewThreadReceived($this->thread));
            return;
        }

        if (!$customer?->email) {
            return;
        }

        // Build per-mailbox mailer config dynamically
        $smtp   = $mailbox->smtp_config;

        // In local env with no SMTP config, fall back to the default mailer (log driver)
        if (!$smtp && app()->environment('local')) {
            broadcast(new NewThreadReceived($this->thread));
            return;
        }

        $mailer = $smtp ? app(DynamicMailerService::class)->fromSmtpConfig($smtp) : Mail::mailer('smtp');

        $thread = $this->thread;

        $agentSignature = $this->thread->user?->signature;

        $mailer->send([], [], function (Message $msg) use ($conversation, $mailbox, $customer, $thread, $agentSignature) {
            $msg->to($customer->email, $customer->name)
                ->from($mailbox->email, $mailbox->name)
                ->subject($this->buildSubject($conversation))
                ->html($this->buildHtmlBody($thread, $mailbox, $agentSignature))
                ->text(strip_tags($thread->body));

            // Set In-Reply-To header to thread the email
            $msgId = $this->conversationMessageId($conversation);
            $msg->getHeaders()->addTextHeader('In-Reply-To', $msgId);
            $msg->getHeaders()->addTextHeader('References', $msgId);
            $msg->getHeaders()->addTextHeader('X-FusterAI-Conversation', (string) $conversation->id);

            // Attach files
            foreach ($thread->attachments as $attachment) {
                if (file_exists(storage_path('app/' . $attachment->path))) {
                    $msg->attach(
                        storage_path('app/' . $attachment->path),
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
            : 'Re: ' . $conversation->subject;
    }

    private function buildHtmlBody(Thread $thread, $mailbox, ?string $agentSignature = null): string
    {
        $body = $thread->body;

        // Agent signature takes priority; fall back to mailbox signature
        $rawSignature = $agentSignature ?? $mailbox->signature ?? null;
        $signature    = $rawSignature
            ? '<br><br>--<br>' . nl2br(e($rawSignature))
            : '';

        return $body . $signature;
    }

    private function conversationMessageId(Conversation $conversation): string
    {
        return '<conversation-' . $conversation->id . '@fusterai>';
    }
}
