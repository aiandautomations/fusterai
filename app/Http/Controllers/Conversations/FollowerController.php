<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowerController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $conversation->followers()->syncWithoutDetaching([$request->user()->id]);

        return back();
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $conversation->followers()->detach($request->user()->id);

        return back();
    }
}
