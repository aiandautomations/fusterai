<?php

namespace App\Http\Controllers;

use App\Domains\Conversation\Models\CustomView;
use App\Http\Requests\CustomView\StoreCustomViewRequest;
use App\Http\Requests\CustomView\UpdateCustomViewRequest;
use App\Services\CustomViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomViewController extends Controller
{
    public function __construct(private CustomViewService $service) {}

    public function store(StoreCustomViewRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $isShared = (bool) ($validated['is_shared'] ?? false);

        if ($isShared && ! $user->hasMinRole('manager')) {
            abort(403, 'Only managers can create shared views.');
        }

        $this->service->create($validated, $user);

        return back()->with('success', 'View created.');
    }

    public function update(UpdateCustomViewRequest $request, CustomView $customView): RedirectResponse
    {
        $user = $request->user();
        $workspaceId = $user->workspace_id;

        abort_if($customView->workspace_id !== $workspaceId, 403);

        if ($customView->is_shared) {
            abort_unless($user->hasMinRole('manager'), 403);
        } else {
            abort_if($customView->user_id !== $user->id, 403);
        }

        $this->service->update($customView, $request->validated());

        return back()->with('success', 'View updated.');
    }

    public function destroy(Request $request, CustomView $customView): RedirectResponse
    {
        $user = $request->user();

        abort_if($customView->workspace_id !== $user->workspace_id, 403);

        if ($customView->is_shared) {
            abort_unless($user->hasMinRole('manager'), 403);
        } else {
            abort_if($customView->user_id !== $user->id, 403);
        }

        $this->service->delete($customView);

        return back()->with('success', 'View deleted.');
    }
}
