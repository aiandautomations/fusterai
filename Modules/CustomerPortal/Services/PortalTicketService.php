<?php

namespace Modules\CustomerPortal\Services;

use App\Domains\Automation\Jobs\RunAutomationRulesJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\ThreadType;
use App\Events\NewThreadReceived;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\NewCustomerReplyNotification;
use App\Support\Hooks;
use Illuminate\Support\Facades\DB;

class PortalTicketService
{
    public function create(Workspace $workspace, Customer $customer, string $subject, string $body): Conversation
    {
        [$conversation, $thread] = DB::transaction(function () use ($workspace, $customer, $subject, $body) {
            $conversation = Conversation::create([
                'workspace_id' => $workspace->id,
                'customer_id' => $customer->id,
                'subject' => $subject,
                'status' => ConversationStatus::Open,
                'channel_type' => ChannelType::Portal,
                'last_reply_at' => now(),
            ]);

            $thread = $conversation->threads()->create([
                'customer_id' => $customer->id,
                'type' => ThreadType::Message,
                'body' => $body,
                'source' => 'portal',
            ]);

            return [$conversation, $thread];
        });

        broadcast(new NewThreadReceived($thread));
        Hooks::doAction('conversation.created', $conversation);
        RunAutomationRulesJob::dispatch('conversation.created', $conversation);

        return $conversation;
    }

    public function reply(Conversation $conversation, Customer $customer, string $body): Thread
    {
        $thread = DB::transaction(function () use ($conversation, $customer, $body) {
            $thread = $conversation->threads()->create([
                'customer_id' => $customer->id,
                'type' => ThreadType::Message,
                'body' => $body,
                'source' => 'portal',
            ]);

            $conversation->update([
                'status' => ConversationStatus::Open,
                'last_reply_at' => now(),
            ]);

            return $thread;
        });

        broadcast(new NewThreadReceived($thread));
        Hooks::doAction('thread.created', $thread);

        if ($conversation->assigned_user_id) {
            User::find($conversation->assigned_user_id)
                ?->notify(new NewCustomerReplyNotification($conversation, $thread));
        }

        return $thread;
    }
}
