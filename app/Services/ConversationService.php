<?php

namespace App\Services;

use App\Domains\AI\Jobs\SummarizeConversationJob;
use App\Domains\Automation\Jobs\RunAutomationRulesJob;
use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Events\NewThreadReceived;
use App\Models\User;
use App\Notifications\ConversationAssignedNotification;
use App\Support\Hooks;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(private AiSettingsService $aiSettings) {}

    /**
     * Create a new outbound conversation with an initial message thread,
     * dispatch the email, and fire automation + module hooks.
     *
     * @return array{conversation: Conversation, thread: Thread}
     */
    public function create(array $validated, int $workspaceId, User $actor): array
    {
        [$conversation, $thread] = DB::transaction(function () use ($validated, $workspaceId, $actor) {
            $conversation = Conversation::create([
                'workspace_id'  => $workspaceId,
                'mailbox_id'    => $validated['mailbox_id'],
                'customer_id'   => $validated['customer_id'],
                'subject'       => $validated['subject'],
                'status'        => 'open',
                'channel_type'  => 'email',
                'last_reply_at' => now(),
            ]);

            $thread = $conversation->threads()->create([
                'user_id' => $actor->id,
                'type'    => 'message',
                'body'    => $validated['body'],
                'source'  => 'web',
            ]);

            return [$conversation, $thread];
        });

        SendReplyJob::dispatch($thread, $conversation)->onQueue('email-outbound');
        RunAutomationRulesJob::dispatch('conversation.created', $conversation);
        Hooks::doAction('conversation.created', $conversation);

        return compact('conversation', 'thread');
    }

    /**
     * Create a new conversation via the public API (resolves/creates the customer,
     * broadcasts events). No activity thread or hooks — this is an API ingest.
     *
     * @return array{conversation: Conversation, thread: Thread}
     */
    public function createViaApi(array $validated, int $workspaceId, User $actor): array
    {
        [$conversation, $thread] = DB::transaction(function () use ($validated, $workspaceId) {
            $customer = Customer::resolveOrCreate(
                $workspaceId,
                $validated['customer_email'],
                $validated['customer_name'] ?? '',
            );

            $conversation = Conversation::create([
                'workspace_id'  => $workspaceId,
                'mailbox_id'    => $validated['mailbox_id'] ?? null,
                'customer_id'   => $customer->id,
                'subject'       => $validated['subject'],
                'status'        => $validated['status'] ?? 'open',
                'priority'      => $validated['priority'] ?? 'normal',
                'channel_type'  => 'api',
                'last_reply_at' => now(),
            ]);

            /** @var Thread $thread */
            $thread = $conversation->threads()->create([
                'customer_id' => $customer->id,
                'type'        => 'message',
                'body'        => nl2br(e($validated['body'])),
                'body_plain'  => $validated['body'],
                'source'      => 'api',
            ]);

            return [$conversation, $thread];
        });

        broadcast(new NewThreadReceived($thread));
        broadcast(new ConversationUpdated($conversation->fresh()));

        return compact('conversation', 'thread');
    }

    /**
     * API-driven field update: applies whichever fields are present (status, priority,
     * assigned_user_id). Fires hooks and broadcast but does not create activity threads
     * since there is no named human actor from the API.
     */
    public function updateViaApi(Conversation $conversation, array $fields, User $actor): Conversation
    {
        if (isset($fields['status'])) {
            $newStatus = ConversationStatus::from($fields['status']);
            $conversation->update(['status' => $newStatus]);

            if ($newStatus === ConversationStatus::Closed) {
                Hooks::doAction('conversation.closed', $conversation->fresh());
                RunAutomationRulesJob::dispatch('conversation.closed', $conversation);
                if ($this->aiSettings->isFeatureEnabled($conversation->workspace_id, 'summarization')) {
                    SummarizeConversationJob::dispatch($conversation)->onQueue('ai');
                }
            }
        }

        if (isset($fields['priority'])) {
            $conversation->update(['priority' => ConversationPriority::from($fields['priority'])]);
        }

        if (array_key_exists('assigned_user_id', $fields)) {
            $conversation->update(['assigned_user_id' => $fields['assigned_user_id']]);
        }

        $fresh = $conversation->fresh();
        Hooks::doAction('conversation.updated', $fresh);
        broadcast(new ConversationUpdated($fresh));

        return $fresh;
    }

    /**
     * Add a reply thread to a conversation via the API and broadcast the event.
     */
    public function replyViaApi(Conversation $conversation, string $body, string $type, User $actor): Thread
    {
        /** @var Thread $thread */
        $thread = $conversation->threads()->create([
            'user_id'    => $actor->id,
            'type'       => $type,
            'body'       => $body,
            'body_plain' => strip_tags($body),
            'source'     => 'api',
        ]);

        $conversation->update(['last_reply_at' => now()]);

        broadcast(new NewThreadReceived($thread->load('user')));
        broadcast(new ConversationUpdated($conversation->fresh()));

        return $thread;
    }

    /**
     * Change conversation status, handling spam blocking, activity thread,
     * hooks, broadcast, and AI summarization on close.
     */
    public function changeStatus(Conversation $conversation, ConversationStatus $newStatus, User $actor): void
    {
        $oldStatus = $conversation->status;
        $updates   = ['status' => $newStatus];

        // Clear snooze when the conversation is actively reopened
        if ($newStatus === ConversationStatus::Open) {
            $updates['snoozed_until'] = null;
        }

        $conversation->update($updates);

        // Block the customer when marked spam; unblock when moving away from spam
        $customer = $conversation->customer;
        if ($customer) {
            if ($newStatus === ConversationStatus::Spam) {
                $customer->update(['is_blocked' => true]);
            } elseif ($oldStatus === ConversationStatus::Spam) {
                $customer->update(['is_blocked' => false]);
            }
        }

        $conversation->threads()->create([
            'user_id' => $actor->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "{$actor->name} changed status from <strong>{$oldStatus->label()}</strong> to <strong>{$newStatus->label()}</strong>",
        ]);

        $conversation->refresh();
        Hooks::doAction('conversation.updated', $conversation);
        broadcast(new ConversationUpdated($conversation));

        if ($newStatus === ConversationStatus::Closed) {
            Hooks::doAction('conversation.closed', $conversation);
            RunAutomationRulesJob::dispatch('conversation.closed', $conversation);
            if ($this->aiSettings->isFeatureEnabled($conversation->workspace_id, 'summarization')) {
                SummarizeConversationJob::dispatch($conversation)->onQueue('ai');
            }
        }
    }

    /**
     * Assign (or unassign) a conversation, with activity thread, hooks,
     * broadcast, and notification to the new assignee.
     */
    public function assign(Conversation $conversation, ?int $assigneeId, User $actor): void
    {
        $conversation->update(['assigned_user_id' => $assigneeId]);

        if ($assigneeId) {
            $assignee = User::find($assigneeId);
            $body     = $assignee?->id === $actor->id
                ? "{$actor->name} self-assigned this conversation"
                : "{$actor->name} assigned this conversation to <strong>{$assignee?->name}</strong>";
        } else {
            $assignee = null;
            $body     = "{$actor->name} unassigned this conversation";
        }

        $conversation->threads()->create([
            'user_id' => $actor->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => $body,
        ]);

        $conversation->refresh();
        Hooks::doAction('conversation.updated', $conversation);
        broadcast(new ConversationUpdated($conversation));

        $assignee?->notify(new ConversationAssignedNotification($conversation));
    }

    /**
     * Snooze a conversation until the given time with an activity thread.
     * Pass $silent = true for bulk operations to suppress the activity thread.
     */
    public function snooze(Conversation $conversation, Carbon $until, User $actor, bool $silent = false): void
    {
        $conversation->update(['snoozed_until' => $until]);

        if (! $silent) {
            $conversation->threads()->create([
                'user_id' => $actor->id,
                'type'    => 'activity',
                'source'  => 'web',
                'body'    => "{$actor->name} snoozed this conversation until <strong>{$until->format('M j, Y g:i A')}</strong>",
            ]);
        }
    }

    /**
     * Change priority with activity thread, hooks, and broadcast.
     * Pass $silent = true for bulk operations to suppress the activity thread.
     */
    public function changePriority(Conversation $conversation, ConversationPriority $newPriority, User $actor, bool $silent = false): void
    {
        $oldPriority = $conversation->priority;
        $conversation->update(['priority' => $newPriority]);

        if (! $silent) {
            $conversation->threads()->create([
                'user_id' => $actor->id,
                'type'    => 'activity',
                'source'  => 'web',
                'body'    => "{$actor->name} changed priority from <strong>{$oldPriority->label()}</strong> to <strong>{$newPriority->label()}</strong>",
            ]);

            $conversation->refresh();
            Hooks::doAction('conversation.updated', $conversation);
            broadcast(new ConversationUpdated($conversation));
        }
    }

    /**
     * Move a conversation to a different mailbox with an activity thread and broadcast.
     */
    public function changeMailbox(Conversation $conversation, int $mailboxId, User $actor): void
    {
        $conversation->update(['mailbox_id' => $mailboxId]);
        $conversation->refresh()->load('mailbox');

        $conversation->threads()->create([
            'user_id' => $actor->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "Conversation moved to mailbox: {$conversation->mailbox->name}",
        ]);

        broadcast(new ConversationUpdated($conversation->fresh()));
    }

    /**
     * Merge $source into $target: moves all threads, logs an activity entry,
     * updates last_reply_at on the target, and deletes the source.
     */
    public function merge(Conversation $source, Conversation $target, User $actor): void
    {
        $source->threads()->update(['conversation_id' => $target->id]);

        $target->threads()->create([
            'user_id' => $actor->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "Conversation #{$source->id} was merged into this conversation.",
        ]);

        $target->update(['last_reply_at' => now()]);
        $source->delete();
    }
}
