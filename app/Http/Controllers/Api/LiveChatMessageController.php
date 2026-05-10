<?php

namespace App\Http\Controllers\Api;

use App\Domains\Conversation\Models\Conversation;
use App\Events\VisitorTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLiveChatMessageRequest;
use App\Models\Workspace;
use App\Services\LiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveChatMessageController extends Controller
{
    public function __construct(private LiveChatService $service) {}

    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate(['workspace_id' => 'required|integer|exists:workspaces,id']);
        $workspace = Workspace::findOrFail($validated['workspace_id']);
        /** @var array<string, mixed> $chat */
        $chat = ($workspace->settings['live_chat'] ?? []) ?: [];

        return response()->json([
            'greeting' => $chat['greeting'] ?? 'Hi there! How can we help?',
            'color' => $chat['color'] ?? '#7c3aed',
            'position' => $chat['position'] ?? 'bottom-right',
            'launcher_text' => $chat['launcher_text'] ?? 'Chat with us',
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'visitor_id' => 'required|string|max:100',
        ]);

        $threads = $this->service->messages($request->visitor_id, (int) $request->conversation_id);

        return response()->json(['threads' => $threads]);
    }

    public function store(StoreLiveChatMessageRequest $request): JsonResponse
    {
        return response()->json($this->service->store($request->validated()));
    }

    public function typing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
        ]);

        broadcast(new VisitorTyping((int) $validated['conversation_id']))->toOthers();

        return response()->json(['ok' => true]);
    }
}
