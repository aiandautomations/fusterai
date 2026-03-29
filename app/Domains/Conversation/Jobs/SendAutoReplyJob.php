<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\Conversation\Models\Conversation;
use App\Services\DynamicMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    public function handle(): void
    {
        $conversation = $this->conversation->load(['mailbox', 'customer']);
        $mailbox      = $conversation->mailbox;
        $customer     = $conversation->customer;

        $autoReply = $mailbox->auto_reply_config;
        if (empty($autoReply['enabled']) || empty($customer->email ?? null)) {
            return;
        }

        $subject = $autoReply['subject'] ?? 'We received your message — ' . $conversation->subject;
        $body    = $autoReply['body'] ?? "Hi {$customer->name},\n\nThank you for reaching out. We've received your message and will get back to you as soon as possible.\n\nBest regards,\n{$mailbox->name}";

        $smtp   = $mailbox->smtp_config;
        $mailer = $smtp ? app(DynamicMailerService::class)->fromSmtpConfig($smtp) : \Illuminate\Support\Facades\Mail::mailer('smtp');

        $mailer->send([], [], function (Message $msg) use ($conversation, $mailbox, $customer, $subject, $body) {
            $msg->to($customer->email, $customer->name)
                ->from($mailbox->email, $mailbox->name)
                ->subject($subject)
                ->text($body);

            $msgId = '<conversation-' . $conversation->id . '@fusterai>';
            $msg->getHeaders()->addTextHeader('Message-ID', $msgId);
            $msg->getHeaders()->addTextHeader('X-FusterAI-AutoReply', '1');
        });
    }
}
