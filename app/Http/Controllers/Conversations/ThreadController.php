<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Enums\ThreadType;
use App\Http\Controllers\Controller;
use App\Services\ThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThreadController extends Controller
{
    public function __construct(private ThreadService $service) {}

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'body'          => ['required', 'string', 'max:50000'],
            'type'          => ['required', Rule::in([ThreadType::Message->value, ThreadType::Note->value])],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

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
