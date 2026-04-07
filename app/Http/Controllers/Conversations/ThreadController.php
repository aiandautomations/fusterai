<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Events\NewThreadReceived;
use App\Http\Controllers\Controller;
use App\Models\ConversationRead;
use App\Notifications\AgentMentionedNotification;
use App\Notifications\ConversationFollowerNotification;
use App\Notifications\NewCustomerReplyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'body'            => ['required', 'string', 'max:50000'],
            'type'            => ['required', 'in:message,note'],
            'attachments'     => ['nullable', 'array'],
            'attachments.*'   => ['file', 'max:20480'],
        ]);

        $isChat = $conversation->channel_type === 'chat';

        /** @var Thread $thread */
        $thread = $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => $validated['type'],
            'body'    => $validated['body'],
            // Use 'chat' source for live chat so NewThreadReceived broadcasts to the
            // public livechat.{id} channel that the visitor widget subscribes to.
            'source'  => $isChat ? 'chat' : 'web',
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/' . $conversation->id, 'local');
                $thread->attachments()->create([
                    'filename'  => $file->getClientOriginalName(),
                    'path'      => $path,
                    'mime_type' => $file->getMimeType(),
                    'size'      => $file->getSize(),
                ]);
            }
        }

        if ($validated['type'] === 'message') {
            $conversation->update(['last_reply_at' => now()]);

            // Mark conversation as read for the agent who just replied
            ConversationRead::markRead($request->user()->id, $conversation->id);

            if ($isChat) {
                // Live chat: broadcast directly to the widget — no email needed.
                broadcast(new NewThreadReceived($thread));
            } else {
                // Broadcast immediately so other agents see the reply in real time,
                // then queue the actual email send separately.
                broadcast(new NewThreadReceived($thread));
                SendReplyJob::dispatch($thread, $conversation)->onQueue('email-outbound');
            }

            // Only notify the assigned agent when a customer (not an agent) sends a message
            $assignedUserId = $conversation->assigned_user_id;
            $isAgentReply   = $thread->user_id !== null;
            if (!$isAgentReply && $assignedUserId && $assignedUserId !== $request->user()->id) {
                $assignee = \App\Models\User::find($assignedUserId);
                $assignee?->notify(new NewCustomerReplyNotification($conversation, $thread));
            }

            // Notify followers (excluding the sender and the assigned agent, who already got notified)
            $notifiedIds = array_filter([$request->user()->id, $assignedUserId]);
            $conversation->followers()
                ->whereNotIn('users.id', $notifiedIds)
                ->each(fn ($follower) => $follower->notify(new ConversationFollowerNotification($conversation, $thread)));
        }

        // Notify @mentioned agents in notes
        if ($validated['type'] === 'note') {
            $this->notifyMentions($thread, $conversation, $request->user());
        }

        return back();
    }

    private function notifyMentions(Thread $thread, Conversation $conversation, \App\Models\User $sender): void
    {
        // Parse data-id attributes from <span data-type="mention" data-id="...">
        preg_match_all('/data-id="(\d+)"/', $thread->body, $matches);
        $mentionedIds = array_unique(array_map('intval', $matches[1]));

        if (empty($mentionedIds)) {
            return;
        }

        \App\Models\User::where('workspace_id', $conversation->workspace_id)
            ->whereIn('id', $mentionedIds)
            ->where('id', '!=', $sender->id)
            ->each(fn (\App\Models\User $user) =>
                $user->notify(new AgentMentionedNotification($conversation, $thread, $sender->name))
            );
    }
}
