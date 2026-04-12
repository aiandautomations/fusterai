<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Conversation\Models\Folder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreFolderRequest;
use App\Http\Requests\Settings\UpdateFolderRequest;
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

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $this->authorize('create', Folder::class);

        $this->service->create($request->validated(), $request->user()->workspace_id, $request->user());

        return back()->with('success', 'Folder created.');
    }

    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $this->service->update($folder, $request->validated());

        return back()->with('success', 'Folder updated.');
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);
        $this->service->delete($folder);

        return back()->with('success', 'Folder deleted.');
    }
}
