<?php

namespace App\Http\Controllers\Mailboxes;

use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailboxes\StoreMailboxRequest;
use App\Http\Requests\Mailboxes\UpdateMailboxRequest;
use App\Services\MailboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailboxController extends Controller
{
    public function __construct(private MailboxService $service) {}

    public function index(Request $request): Response
    {
        $mailboxes = Mailbox::where('workspace_id', $request->user()->workspace_id)
            ->withCount([
                'conversations',
                'conversations as open_count' => fn ($q) => $q->where('status', 'open'),
                'conversations as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->get();

        return Inertia::render('Mailboxes/Index', [
            'mailboxes' => $mailboxes,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Mailbox::class);

        return Inertia::render('Mailboxes/Create');
    }

    public function store(StoreMailboxRequest $request): RedirectResponse
    {
        $this->authorize('create', Mailbox::class);

        $this->service->create($request->validated(), $request->user()->workspace_id);

        return redirect()->route('mailboxes.index')->with('success', 'Mailbox created.');
    }

    public function edit(Request $request, Mailbox $mailbox): Response
    {
        $this->authorize('update', $mailbox);

        return Inertia::render('Mailboxes/Settings', [
            'mailbox' => $mailbox,
        ]);
    }

    public function update(UpdateMailboxRequest $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorize('update', $mailbox);

        $this->service->update($mailbox, $request->validated());

        return back()->with('success', 'Mailbox updated.');
    }

    public function destroy(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorize('delete', $mailbox);
        $this->service->delete($mailbox);

        return redirect()->route('mailboxes.index')->with('success', 'Mailbox deleted.');
    }
}
