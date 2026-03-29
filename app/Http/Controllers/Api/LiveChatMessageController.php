<?php

namespace App\Http\Controllers\Api;

use App\Domains\Channel\Jobs\HandleLiveChatMessageJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveChatMessageController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate(['workspace_id' => 'required|integer|exists:workspaces,id']);
        $workspace  = Workspace::findOrFail($validated['workspace_id']);
        /** @var array<string, mixed> $chat */
        $chat       = ($workspace->settings['live_chat'] ?? []) ?: [];

        return response()->json([
            'greeting'      => $chat['greeting']      ?? 'Hi there! How can we help?',
            'color'         => $chat['color']         ?? '#7c3aed',
            'position'      => $chat['position']      ?? 'bottom-right',
            'launcher_text' => $chat['launcher_text'] ?? 'Chat with us',
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'visitor_id'      => 'required|string|max:100',
        ]);

        $email    = "visitor_{$request->visitor_id}@livechat.local";
        $customer = Customer::where('email', $email)->first();

        if (!$customer) {
            return response()->json(['threads' => []]);
        }

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('customer_id', $customer->id)
            ->where('channel_type', 'chat')
            ->first();

        if (!$conversation) {
            return response()->json(['threads' => []]);
        }

        $threads = $conversation->threads()
            ->with(['user:id,name,avatar', 'customer:id,name'])
            ->where('type', 'message')
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'customer_id', 'body', 'created_at']);

        return response()->json(['threads' => $threads]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id'  => 'required|integer|exists:workspaces,id',
            'visitor_id'    => 'required|string|max:100',
            'visitor_name'  => 'nullable|string|max:100',
            'visitor_email' => 'nullable|email|max:255',
            'message'       => 'required|string|max:5000',
        ]);

        // Resolve customer and conversation synchronously so we can return
        // the conversation_id immediately — the widget needs it to subscribe
        // to the real-time channel for agent replies.
        $email    = !empty($validated['visitor_email'])
            ? $validated['visitor_email']
            : "visitor_{$validated['visitor_id']}@livechat.local";

        $customer = Customer::firstOrCreate(
            ['workspace_id' => $validated['workspace_id'], 'email' => $email],
            ['name' => $validated['visitor_name'] ?? 'Visitor'],
        );

        $conversation = Conversation::where('workspace_id', $validated['workspace_id'])
            ->where('customer_id', $customer->id)
            ->where('channel_type', 'chat')
            ->where('status', 'open')
            ->latest()
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'workspace_id'  => $validated['workspace_id'],
                'customer_id'   => $customer->id,
                'subject'       => 'Live chat with ' . ($validated['visitor_name'] ?? 'Visitor'),
                'status'        => 'open',
                'channel_type'  => 'chat',
                'channel_id'    => $validated['visitor_id'],
                'last_reply_at' => now(),
            ]);
        }

        HandleLiveChatMessageJob::dispatch(
            $conversation,
            $customer,
            $validated['message'],
        )->onQueue('default');

        return response()->json([
            'status'          => 'sent',
            'conversation_id' => $conversation->id,
        ]);
    }
}
