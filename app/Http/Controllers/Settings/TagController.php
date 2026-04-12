<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Conversation\Models\Tag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTagRequest;
use App\Http\Requests\Settings\UpdateTagRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::where('workspace_id', $request->user()->workspace_id)
            ->withCount('conversations')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/Tags', ['tags' => $tags]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $this->authorize('create', Tag::class);

        Tag::create(['workspace_id' => $request->user()->workspace_id, ...$request->validated()]);

        return back();
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());
        return back();
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);
        $tag->delete();
        return back();
    }
}
