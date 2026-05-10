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
use App\Models\User;
use App\Notifications\NewCustomerReplyNotification;
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

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $mailboxId,
        public readonly array $emailData,
    ) {}

    public function handle(): void
    {
        $mailbox = Mailbox::find($this->mailboxId);
        if (! $mailbox) {
            return;
        }

        $data = $this->emailData;

        // Silently drop auto-replies/OOO emails to prevent infinite loops.
        if ($this->isAutoReply($data)) {
            return;
        }

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
                'type' => 'message',
                'body' => $body,
                'body_plain' => strip_tags($body),
                'source' => 'email',
                'meta' => [
                    'message_id' => $data['message_id'],
                    'in_reply_to' => $data['in_reply_to'],
                    'from_email' => $data['from_email'],
                    'cc' => $data['cc'] ?? [],
                ],
            ]);

            // Save attachments
            foreach ($data['attachments'] ?? [] as $att) {
                $decoded = base64_decode($att['content'], true);
                if ($decoded === false) {
                    continue; // skip malformed attachment content
                }
                $safeFilename = Str::slug(pathinfo($att['name'], PATHINFO_FILENAME))
                    .'.'.pathinfo($att['name'], PATHINFO_EXTENSION);
                $path = 'attachments/'.$conversation->id.'/'.Str::uuid().'_'.$safeFilename;
                Storage::put($path, $decoded);
                $thread->attachments()->create([
                    'filename' => $att['name'],
                    'path' => $path,
                    'mime_type' => $att['mime'],
                    'size' => $att['size'] ?? 0,
                ]);
            }

            $conversation->update([
                'status' => 'open',
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

        // Notify assigned agent of the new customer reply
        if ($conversation->assigned_user_id) {
            User::find($conversation->assigned_user_id)
                ?->notify(new NewCustomerReplyNotification($conversation, $thread));
        }

        // Auto-mark spam and skip auto-reply for blocked customers (repeat spammers)
        if ($conversation->wasRecentlyCreated) {
            $customer = Customer::find($conversation->customer_id);
            if ($customer?->is_blocked) {
                $conversation->update(['status' => 'spam']);
                broadcast(new ConversationUpdated($conversation->fresh()));
            } else {
                SendAutoReplyJob::dispatch($conversation)->onQueue('email-outbound');
            }
        }
    }

    private function resolveConversation(Mailbox $mailbox, Customer $customer, array $data): Conversation
    {
        // Try to match by In-Reply-To or References to an existing conversation
        if (! empty($data['in_reply_to']) || ! empty($data['references'])) {
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
                'workspace_id' => $mailbox->workspace_id,
                'customer_id' => $customer->id,
                'subject' => $this->normalizeSubject($data['subject'] ?: '(No Subject)'),
                'status' => 'open',
                'channel_type' => 'email',
                'last_reply_at' => now(),
            ],
        );
    }

    /**
     * Strip common forward prefixes so "Fwd: Fw: Your invoice" becomes "Your invoice".
     */
    private function normalizeSubject(string $subject): string
    {
        // Iteratively strip leading Fwd:/Fw:/Re: prefixes (any combination)
        do {
            $prev = $subject;
            $subject = preg_replace('/^(fwd?|re)\s*:\s*/i', '', trim($subject));
        } while ($subject !== $prev);

        return $subject ?: '(No Subject)';
    }

    /**
     * Detect auto-replies, out-of-office, and our own auto-reply emails.
     * Returns true if the email should be silently dropped.
     */
    private function isAutoReply(array $data): bool
    {
        $headers = $data['headers'] ?? [];

        // Our own auto-reply header
        if (! empty($headers['x_fusterai_auto_reply'])) {
            return true;
        }

        // RFC 3834 — Auto-Submitted header (anything except "no")
        $autoSubmitted = strtolower(trim($headers['auto_submitted'] ?? ''));
        if ($autoSubmitted && $autoSubmitted !== 'no') {
            return true;
        }

        // X-Auto-Response-Suppress present means the sender requests no auto-reply
        // but it also signals this email itself is automated
        if (! empty($headers['x_auto_response_suppress'])) {
            return true;
        }

        // Precedence: bulk/junk/list are mass-mailing signals — skip auto-reply
        $precedence = strtolower(trim($headers['precedence'] ?? ''));
        if (in_array($precedence, ['bulk', 'junk', 'list'], true)) {
            return true;
        }

        // Subject-line heuristics for OOO when headers are missing
        $subject = strtolower($data['subject'] ?? '');
        foreach (['out of office', 'automatic reply', 'auto-reply', 'autoreply', 'vacation reply'] as $marker) {
            if (str_contains($subject, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip quoted reply text (lines starting with > or common separators).
     */
    private function stripQuotedReply(string $html): string
    {
        // Remove blockquote elements (quoted replies in HTML emails)
        $html = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/is', '', $html) ?? $html;

        // Remove common reply separators and everything after
        $separators = [
            'On .* wrote:',
            '-----Original Message-----',
            'From:.*Sent:.*To:.*Subject:',
        ];

        foreach ($separators as $sep) {
            $html = preg_replace('/('.$sep.').*$/is', '', $html) ?? $html;
        }

        return trim($html);
    }
}
