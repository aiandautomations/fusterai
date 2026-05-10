<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Enums\ThreadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreThreadRequest;
use App\Services\ThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function __construct(private ThreadService $service) {}

    public function store(StoreThreadRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validated();

        $this->service->store(
            $conversation,
            $validated['body'],
            ThreadType::from($validated['type']),
            $request->user(),
            $request->file('attachments') ?? [],
            isset($validated['send_at']) ? new \Carbon\Carbon($validated['send_at']) : null,
        );

        return back();
    }

    public function cancelSchedule(Request $request, Conversation $conversation, Thread $thread): JsonResponse
    {
        $this->authorize('update', $conversation);

        abort_unless(
            $thread->conversation_id === $conversation->id && $thread->send_at !== null,
            404,
        );

        $thread->update(['send_at' => null]);

        return response()->json(['ok' => true]);
    }
}
