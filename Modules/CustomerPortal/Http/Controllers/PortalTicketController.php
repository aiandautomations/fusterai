<?php

namespace Modules\CustomerPortal\Http\Controllers;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Enums\ThreadType;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CustomerPortal\Http\Requests\NewTicketRequest;
use Modules\CustomerPortal\Http\Requests\ReplyTicketRequest;
use Modules\CustomerPortal\Services\PortalTicketService;

class PortalTicketController extends Controller
{
    public function __construct(private PortalTicketService $service) {}

    public function index(Workspace $workspace): Response
    {
        $customer = $this->portalCustomer();

        $tickets = Conversation::where('workspace_id', $workspace->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_reply_at')
            ->paginate(20, ['id', 'subject', 'status', 'priority', 'last_reply_at', 'created_at']);

        return Inertia::render('Portal/Tickets/Index', [
            'workspace' => $this->workspaceProps($workspace),
            'customer' => ['name' => $customer->name, 'email' => $customer->email],
            'tickets' => $tickets->through(fn ($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status->value,
                'priority' => $t->priority->value,
                'last_reply_at' => $t->last_reply_at?->toISOString(),
                'created_at' => $t->created_at->toISOString(),
            ]),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        return Inertia::render('Portal/Tickets/Create', [
            'workspace' => $this->workspaceProps($workspace),
        ]);
    }

    public function store(NewTicketRequest $request, Workspace $workspace): RedirectResponse
    {
        $customer = $this->portalCustomer();
        $validated = $request->validated();

        $conversation = $this->service->create(
            $workspace,
            $customer,
            $validated['subject'],
            $validated['body'],
        );

        return redirect()->route('portal.tickets.show', [$workspace->slug, $conversation->id])
            ->with('success', 'Your ticket has been submitted.');
    }

    public function show(Workspace $workspace, Conversation $conversation): Response
    {
        $this->authorizeTicket($workspace, $conversation);

        $conversation->load(['threads' => fn ($q) => $q->where('type', ThreadType::Message->value)->with('user')]);

        return Inertia::render('Portal/Tickets/Show', [
            'workspace' => $this->workspaceProps($workspace),
            'ticket' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status->value,
                'priority' => $conversation->priority->value,
                'created_at' => $conversation->created_at->toISOString(),
                'threads' => $conversation->threads->map(fn ($t) => [
                    'id' => $t->id,
                    'body' => $t->body,
                    'from_customer' => $t->isFromCustomer(),
                    'author' => $t->authorName(),
                    'created_at' => $t->created_at->toISOString(),
                ]),
            ],
        ]);
    }

    public function reply(ReplyTicketRequest $request, Workspace $workspace, Conversation $conversation): RedirectResponse
    {
        $customer = $this->portalCustomer();
        $this->authorizeTicket($workspace, $conversation);

        $this->service->reply($conversation, $customer, $request->validated('body'));

        return back()->with('success', 'Reply sent.');
    }

    private function portalCustomer(): Customer
    {
        /** @var Customer */
        return Auth::guard('customer_portal')->user();
    }

    private function authorizeTicket(Workspace $workspace, Conversation $conversation): void
    {
        $customer = $this->portalCustomer();

        if ($conversation->workspace_id !== $workspace->id || $conversation->customer_id !== $customer->id) {
            abort(403);
        }
    }

    private function workspaceProps(Workspace $workspace): array
    {
        $portal = $workspace->settings['portal'] ?? [];

        return [
            'name' => $portal['name'] ?? $workspace->name.' Support',
            'slug' => $workspace->slug,
        ];
    }
}
