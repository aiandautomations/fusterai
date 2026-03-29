<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Conversation\Models\Folder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Folder::class);

        $folders = Folder::where('workspace_id', $request->user()->workspace_id)
            ->withCount('conversations')
            ->orderBy('order')
            ->get();

        return Inertia::render('Settings/Folders', ['folders' => $folders]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Folder::class);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon'  => ['nullable', 'string', 'max:50'],
        ]);

        $workspaceId = $request->user()->workspace_id;

        Folder::create([
            'workspace_id'       => $workspaceId,
            'created_by_user_id' => $request->user()->id,
            'name'               => $validated['name'],
            'color'              => $validated['color'],
            'icon'               => $validated['icon'] ?? 'folder',
            'order'              => Folder::where('workspace_id', $workspaceId)->max('order') + 1,
        ]);

        return back()->with('success', 'Folder created.');
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon'  => ['sometimes', 'string', 'max:50'],
        ]);

        $folder->update($validated);

        return back()->with('success', 'Folder updated.');
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);
        $folder->delete();

        return back()->with('success', 'Folder deleted.');
    }
}
