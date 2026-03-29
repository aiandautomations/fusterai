<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Conversation\Models\Tag;
use App\Http\Controllers\Controller;
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tag::class);

        $workspaceId = $request->user()->workspace_id;

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:50', "unique:tags,name,NULL,id,workspace_id,{$workspaceId}"],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        Tag::create(['workspace_id' => $workspaceId, ...$validated]);

        return back();
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $workspaceId = $request->user()->workspace_id;

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:50', "unique:tags,name,{$tag->id},id,workspace_id,{$workspaceId}"],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);
        return back();
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);
        $tag->delete();
        return back();
    }
}
