<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CannedResponseController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = $request->user()->workspace_id;

        $responses = CannedResponse::where('workspace_id', $workspaceId)
            ->with('mailbox:id,name')
            ->orderBy('mailbox_id')
            ->orderBy('name')
            ->get();

        $mailboxes = Mailbox::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Settings/CannedResponses', [
            'responses' => $responses,
            'mailboxes' => $mailboxes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'content'    => ['required', 'string'],
            'mailbox_id' => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $request->user()->workspace_id)],
        ]);

        CannedResponse::create([
            'workspace_id' => $request->user()->workspace_id,
            ...$validated,
        ]);

        return back()->with('success', 'Canned response created.');
    }

    public function update(Request $request, CannedResponse $cannedResponse)
    {
        $this->authorize('update', $cannedResponse);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'content'    => ['required', 'string'],
            'mailbox_id' => ['nullable', 'integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $request->user()->workspace_id)],
        ]);

        $cannedResponse->update($validated);

        return back()->with('success', 'Canned response updated.');
    }

    public function destroy(Request $request, CannedResponse $cannedResponse)
    {
        $this->authorize('delete', $cannedResponse);
        $cannedResponse->delete();

        return back()->with('success', 'Canned response deleted.');
    }

    // API endpoint — search canned responses for in-reply dropdown
    // Returns workspace-wide responses + mailbox-specific ones (when mailbox_id provided)
    public function search(Request $request)
    {
        $query      = $request->get('q', '');
        $mailboxId  = $request->get('mailbox_id');
        $workspaceId = $request->user()->workspace_id;

        $responses = CannedResponse::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($mailboxId) {
                // Include workspace-wide (null) and mailbox-specific responses
                $q->whereNull('mailbox_id');
                if ($mailboxId) {
                    $q->orWhere('mailbox_id', $mailboxId);
                }
            })
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                  ->orWhere('content', 'ilike', "%{$query}%");
            })
            ->orderByRaw('mailbox_id IS NULL ASC') // mailbox-specific first
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'content', 'mailbox_id']);

        return response()->json($responses);
    }
}
