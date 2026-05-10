<?php

namespace App\Services;

use App\Domains\Conversation\Jobs\SendReplyJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ChannelType;
use App\Enums\ThreadType;
use App\Events\NewThreadReceived;
use App\Models\ConversationRead;
use App\Models\User;
use App\Notifications\AgentMentionedNotification;
use App\Notifications\ConversationFollowerNotification;
use App\Notifications\NewCustomerReplyNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class ThreadService
{
    /**
     * Create a thread (reply or note) on a conversation, handling:
     * - Attachment storage
     * - Email vs live-chat dispatch
     * - Real-time broadcast
     * - Notifications (assigned agent, followers, @mentions)
     *
     * @param  UploadedFile[]  $files
     */
    public function store(Conversation $conversation, string $body, ThreadType $type, User $actor, array $files = [], ?Carbon $sendAt = null): Thread
    {
        $isChat = $conversation->channel_type === ChannelType::Chat;

        /** @var Thread $thread */
        $thread = $conversation->threads()->create([
            'user_id' => $actor->id,
            'type' => $type,
            'body' => $body,
            'source' => $isChat ? 'chat' : 'web',
            'send_at' => $sendAt,
        ]);

        foreach ($files as $file) {
            $path = $file->store('attachments/'.$conversation->id, config('filesystems.default'));
            $thread->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($type === ThreadType::Message) {
            $conversation->update(['last_reply_at' => now()]);
            ConversationRead::markRead($actor->id, $conversation->id);

            if (! $sendAt) {
                broadcast(new NewThreadReceived($thread));
            }

            if (! $isChat) {
                $pending = SendReplyJob::dispatch($thread, $conversation, $sendAt)->onQueue('email-outbound');
                if ($sendAt) {
                    $pending->delay($sendAt);
                }
            }

            $this->notifyOnReply($conversation, $thread, $actor);
        }

        if ($type === ThreadType::Note) {
            $this->notifyMentions($thread, $conversation, $actor);
        }

        return $thread;
    }

    private function notifyOnReply(Conversation $conversation, Thread $thread, User $actor): void
    {
        $assignedUserId = $conversation->assigned_user_id;

        // Only notify the assigned agent for customer-originated replies, not agent replies.
        // Agent reply notifications are handled in ProcessInboundEmailJob.
        $isAgentReply = $thread->user_id !== null;
        if (! $isAgentReply && $assignedUserId && $assignedUserId !== $actor->id) {
            User::find($assignedUserId)?->notify(new NewCustomerReplyNotification($conversation, $thread));
        }

        // Notify followers, excluding the sender and the assigned agent (already notified above)
        $notifiedIds = array_filter([$actor->id, $assignedUserId]);
        $followers = $conversation->followers()->whereNotIn('users.id', $notifiedIds)->get();
        Notification::send($followers, new ConversationFollowerNotification($conversation, $thread));
    }

    private function notifyMentions(Thread $thread, Conversation $conversation, User $sender): void
    {
        preg_match_all('/data-id="(\d+)"/', $thread->body, $matches);
        $mentionedIds = array_unique(array_map('intval', $matches[1]));

        if (empty($mentionedIds)) {
            return;
        }

        $mentioned = User::where('workspace_id', $conversation->workspace_id)
            ->whereIn('id', $mentionedIds)
            ->where('id', '!=', $sender->id)
            ->get();

        Notification::send($mentioned, new AgentMentionedNotification($conversation, $thread, $sender->name));
    }
}
