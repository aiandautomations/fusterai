<?php

namespace App\Http\Controllers;

use App\Domains\Conversation\Models\CustomView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomViewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user        = $request->user();
        $workspaceId = $user->workspace_id;

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:100'],
            'color'                => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_shared'            => ['boolean'],
            'filters'              => ['required', 'array'],
            'filters.status'       => ['nullable', 'in:open,pending,closed,spam,snoozed'],
            'filters.assigned'     => ['nullable', 'string', 'max:20'],
            'filters.mailbox_id'   => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'filters.tag_id'       => ['nullable', 'integer', Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
            'filters.priority'     => ['nullable', 'in:low,normal,high,urgent'],
            'filters.channel_type' => ['nullable', 'in:email,chat,whatsapp,slack,api,sms'],
        ]);

        $isShared = (bool) ($validated['is_shared'] ?? false);

        if ($isShared && ! $user->hasMinRole('manager')) {
            abort(403, 'Only managers can create shared views.');
        }

        CustomView::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $isShared ? null : $user->id,
            'name'         => $validated['name'],
            'color'        => $validated['color'],
            'filters'      => $this->stripEmpty($validated['filters']),
            'is_shared'    => $isShared,
            'order'        => CustomView::where('workspace_id', $workspaceId)->max('order') + 1,
        ]);

        return back()->with('success', 'View created.');
    }

    public function update(Request $request, CustomView $customView): RedirectResponse
    {
        $user        = $request->user();
        $workspaceId = $user->workspace_id;

        abort_if($customView->workspace_id !== $workspaceId, 403);

        if ($customView->is_shared) {
            abort_unless($user->hasMinRole('manager'), 403);
        } else {
            abort_if($customView->user_id !== $user->id, 403);
        }

        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:100'],
            'color'                => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'filters'              => ['sometimes', 'array'],
            'filters.status'       => ['nullable', 'in:open,pending,closed,spam,snoozed'],
            'filters.assigned'     => ['nullable', 'string', 'max:20'],
            'filters.mailbox_id'   => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'filters.tag_id'       => ['nullable', 'integer', Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
            'filters.priority'     => ['nullable', 'in:low,normal,high,urgent'],
            'filters.channel_type' => ['nullable', 'in:email,chat,whatsapp,slack,api,sms'],
        ]);

        if (isset($validated['filters'])) {
            $validated['filters'] = $this->stripEmpty($validated['filters']);
        }

        $customView->update($validated);

        return back()->with('success', 'View updated.');
    }

    private function stripEmpty(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null && $v !== '');
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

        $customView->delete();

        return back()->with('success', 'View deleted.');
    }
}
