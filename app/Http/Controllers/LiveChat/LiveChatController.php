<?php

namespace App\Http\Controllers\LiveChat;

use App\Domains\Conversation\Models\Conversation;
use App\Events\AgentTyping;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('workspace_id', $user->workspace_id)
            ->where('channel_type', 'chat')
            ->where('status', 'open')
            ->with(['customer', 'threads' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_reply_at')
            ->get();

        return Inertia::render('LiveChat/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        // Ensure conversation belongs to agent's workspace
        abort_unless($conversation->workspace_id === $request->user()->workspace_id, 403);

        broadcast(new AgentTyping($conversation->id, $request->user()->name))->toOthers();

        return response()->json(['ok' => true]);
    }
}
