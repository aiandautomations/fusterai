<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Conversation\Models\Folder;
use App\Http\Controllers\Controller;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    public function __construct(private FolderService $service) {}

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

        $this->service->create($validated, $request->user()->workspace_id, $request->user());

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

        $this->service->update($folder, $validated);

        return back()->with('success', 'Folder updated.');
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);
        $this->service->delete($folder);

        return back()->with('success', 'Folder deleted.');
    }
}
