<?php

namespace App\Domains\Conversation\Jobs;

use App\Domains\AI\Jobs\CategorizeConversationJob;
use App\Domains\AI\Jobs\GenerateReplySuggestionJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\ConversationUpdated;
use App\Events\NewThreadReceived;
use App\Services\AiSettingsService;
use App\Support\Hooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int   $mailboxId,
        public readonly array $emailData,
    ) {}

    public function handle(): void
    {
        $mailbox = Mailbox::find($this->mailboxId);
        if (!$mailbox) return;

        $data = $this->emailData;

        // Build thread body — prefer HTML, fallback to text
        $body = $data['body_html'] ?: nl2br(e($data['body_text'] ?? ''));
        $body = $this->stripQuotedReply($body);

        [$conversation, $thread] = DB::transaction(function () use ($mailbox, $data, $body) {
            // Find or create customer
            $customer = Customer::resolveOrCreate(
                $mailbox->workspace_id,
                $data['from_email'],
                $data['from_name'] ?? '',
            );

            // Find existing conversation (by In-Reply-To / References header) or create new
            $conversation = $this->resolveConversation($mailbox, $customer, $data);

            /** @var Thread $thread */
            $thread = $conversation->threads()->create([
                'customer_id' => $customer->id,
                'type'        => 'message',
                'body'        => $body,
                'body_plain'  => strip_tags($body),
                'source'      => 'email',
                'meta'        => [
                    'message_id'  => $data['message_id'],
                    'in_reply_to' => $data['in_reply_to'],
                    'from_email'  => $data['from_email'],
                ],
            ]);

            // Save attachments
            foreach ($data['attachments'] ?? [] as $att) {
                $safeFilename = Str::slug(pathinfo($att['name'], PATHINFO_FILENAME))
                    . '.' . pathinfo($att['name'], PATHINFO_EXTENSION);
                $path = 'attachments/' . $conversation->id . '/' . Str::uuid() . '_' . $safeFilename;
                Storage::put($path, base64_decode($att['content']));
                $thread->attachments()->create([
                    'filename'  => $att['name'],
                    'path'      => $path,
                    'mime_type' => $att['mime'],
                    'size'      => $att['size'] ?? 0,
                ]);
            }

            $conversation->update([
                'status'        => 'open',
                'last_reply_at' => now(),
            ]);

            return [$conversation, $thread];
        });

        // Fire module hooks
        if ($conversation->wasRecentlyCreated) {
            Hooks::doAction('conversation.created', $conversation);
        }
        Hooks::doAction('thread.created', $thread);

        // Broadcast real-time update — load relations the frontend expects
        broadcast(new NewThreadReceived($thread->load(['user', 'customer', 'attachments'])));
        broadcast(new ConversationUpdated($conversation));

        // Trigger AI jobs — read workspace-level feature flags via AiSettingsService
        $ai = app(AiSettingsService::class);
        if ($ai->isFeatureEnabled($mailbox->workspace_id, 'reply_suggestions')) {
            GenerateReplySuggestionJob::dispatch($conversation)->onQueue('ai');
        }
        if ($ai->isFeatureEnabled($mailbox->workspace_id, 'auto_categorization') && $conversation->wasRecentlyCreated) {
            CategorizeConversationJob::dispatch($conversation)->onQueue('ai');
        }

        // Auto-mark spam and skip auto-reply for blocked customers (repeat spammers)
        if ($conversation->wasRecentlyCreated) {
            $customer = Customer::find($conversation->customer_id);
            if ($customer?->is_blocked) {
                $conversation->update(['status' => 'spam']);
            } else {
                SendAutoReplyJob::dispatch($conversation)->onQueue('email-outbound');
            }
        }
    }

    private function resolveConversation(Mailbox $mailbox, Customer $customer, array $data): Conversation
    {
        // Try to match by In-Reply-To or References to an existing conversation
        if (!empty($data['in_reply_to']) || !empty($data['references'])) {
            $ref = $data['in_reply_to'] ?: $data['references'];
            // Extract conversation ID from our message-id format: <conversation-123@fusterai>
            if (preg_match('/conversation-(\d+)@fusterai/', $ref, $m)) {
                $existing = Conversation::where('mailbox_id', $mailbox->id)
                    ->find((int) $m[1]);
                if ($existing) {
                    return $existing;
                }
            }
        }

        // firstOrCreate on channel_id prevents duplicate conversations when the
        // same email is processed more than once (e.g. duplicate IMAP fetch).
        return Conversation::firstOrCreate(
            [
                'mailbox_id' => $mailbox->id,
                'channel_id' => $data['message_id'],
            ],
            [
                'workspace_id'  => $mailbox->workspace_id,
                'customer_id'   => $customer->id,
                'subject'       => $data['subject'] ?: '(No Subject)',
                'status'        => 'open',
                'channel_type'  => 'email',
                'last_reply_at' => now(),
            ],
        );
    }

    /**
     * Strip quoted reply text (lines starting with > or common separators).
     */
    private function stripQuotedReply(string $html): string
    {
        // Remove blockquote elements (quoted replies in HTML emails)
        $html = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/is', '', $html);

        // Remove common reply separators and everything after
        $separators = [
            'On .* wrote:',
            '-----Original Message-----',
            'From:.*Sent:.*To:.*Subject:',
        ];

        foreach ($separators as $sep) {
            $html = preg_replace('/(' . $sep . ').*$/is', '', $html);
        }

        return trim($html);
    }
}
