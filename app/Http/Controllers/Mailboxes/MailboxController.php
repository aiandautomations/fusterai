<?php

namespace App\Http\Controllers\Mailboxes;

use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
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
                'conversations as open_count'    => fn ($q) => $q->where('status', 'open'),
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Mailbox::class);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
        ]);

        $this->service->create($validated, $request->user()->workspace_id);

        return redirect()->route('mailboxes.index')->with('success', 'Mailbox created.');
    }

    public function edit(Request $request, Mailbox $mailbox): Response
    {
        $this->authorize('update', $mailbox);

        return Inertia::render('Mailboxes/Settings', [
            'mailbox' => $mailbox,
        ]);
    }

    public function update(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorize('update', $mailbox);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'email'       => ['sometimes', 'email'],
            'signature'   => ['nullable', 'string'],
            'active'      => ['sometimes', 'boolean'],
            'imap_config' => ['nullable', 'array'],
            'smtp_config' => ['nullable', 'array'],
            'auto_reply_config'                         => ['nullable', 'array'],
            'auto_reply_config.enabled'                 => ['boolean'],
            'auto_reply_config.subject'                 => ['nullable', 'string', 'max:255'],
            'auto_reply_config.body'                    => ['nullable', 'string'],
            'auto_reply_config.auto_close_pending_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $this->service->update($mailbox, $validated);

        return back()->with('success', 'Mailbox updated.');
    }

    public function destroy(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorize('delete', $mailbox);
        $this->service->delete($mailbox);

        return redirect()->route('mailboxes.index')->with('success', 'Mailbox deleted.');
    }
}
