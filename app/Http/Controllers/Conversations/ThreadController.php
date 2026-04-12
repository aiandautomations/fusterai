<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Enums\ThreadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreThreadRequest;
use App\Services\ThreadService;
use Illuminate\Http\RedirectResponse;

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
        );

        return back();
    }
}
