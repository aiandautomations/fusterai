<?php

namespace App\Http\Controllers\LiveChat;

use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
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
}
