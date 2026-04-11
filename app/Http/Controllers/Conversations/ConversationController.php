<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Folder;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Mailbox\Models\Mailbox;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\ConversationRead;
use App\Services\ConversationService;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $service) {}
    public function index(Request $request): Response
    {
        $user = $request->user();

        $request->validate([
            'status'    => ['nullable', Rule::in([...array_column(ConversationStatus::cases(), 'value'), 'snoozed'])],
            'priority'  => ['nullable', Rule::enum(ConversationPriority::class)],
            'mailbox'   => 'nullable|integer',
            'assigned'  => ['nullable', 'regex:/^(me|none|all|\d+)$/'],
            'tag'       => 'nullable|integer',
            'folder'    => 'nullable|integer',
            'view'      => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Conversation::query()
            ->where('workspace_id', $user->workspace_id)
            ->with(['customer', 'mailbox', 'assignedUser', 'tags'])
            ->orderByDesc('last_reply_at');

        // Resolve custom view and merge its filters (URL params take precedence)
        $activeView = null;
        if ($viewId = $request->get('view')) {
            $activeView = \App\Domains\Conversation\Models\CustomView::where('workspace_id', $user->workspace_id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('is_shared', true);
                })
                ->find($viewId);

            if ($activeView) {
                $vf = $activeView->filters ?? [];
                $request->mergeIfMissing(array_filter([
                    'status'   => $vf['status']     ?? null,
                    'assigned' => $vf['assigned']   ?? null,
                    'mailbox'  => isset($vf['mailbox_id']) ? (string) $vf['mailbox_id'] : null,
                    'tag'      => isset($vf['tag_id'])     ? (string) $vf['tag_id']     : null,
                    'priority' => $vf['priority']   ?? null,
                ], fn ($v) => $v !== null));

                if (!empty($vf['channel_type']) && !$request->has('channel_type')) {
                    $query->where('channel_type', $vf['channel_type']);
                }
            }
        }

        $status = $request->get('status', 'open');
        if ($status === 'snoozed') {
            $query->snoozed();
        } else {
            $query->where('status', $status)->whereNull('snoozed_until');
        }

        if ($mailboxId = $request->get('mailbox')) {
            $query->where('mailbox_id', $mailboxId);
        }

        $assigned = $request->get('assigned');
        if ($assigned === 'me') {
            $query->where('assigned_user_id', $user->id);
        } elseif ($assigned === 'none') {
            $query->whereNull('assigned_user_id');
        } elseif (is_numeric($assigned)) {
            $query->where('assigned_user_id', (int) $assigned);
        }

        if ($tagId = $request->get('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($folderId = $request->get('folder')) {
            $query->whereHas('folders', fn($q) => $q->where('folders.id', $folderId));
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $conversations = $query->paginate(30)->withQueryString();

        // Attach is_unread flag — a conversation is unread if the user has never
        // opened it, or if last_reply_at is newer than their last read timestamp.
        $convIds = $conversations->pluck('id')->all();
        $readMap = ConversationRead::where('user_id', $user->id)
            ->whereIn('conversation_id', $convIds)
            ->pluck('last_read_at', 'conversation_id');

        $conversations->getCollection()->transform(function (Conversation $conv) use ($readMap) {
            $lastRead = $readMap->get($conv->id);
            $conv->is_unread = $lastRead === null
                ? $conv->last_reply_at !== null
                : ($conv->last_reply_at !== null && $conv->last_reply_at->gt($lastRead));
            return $conv;
        });

        $mailboxes     = Mailbox::where('workspace_id', $user->workspace_id)->get(['id', 'name', 'email']);
        $tags          = Tag::where('workspace_id', $user->workspace_id)->get(['id', 'name', 'color']);
        $folders       = Folder::where('workspace_id', $user->workspace_id)->orderBy('order')->get(['id', 'name', 'color', 'icon']);
        $counts        = $this->folderCounts($user);
        $agents        = \App\Models\User::where('workspace_id', $user->workspace_id)->get(['id', 'name', 'avatar', 'status']);

        $selected = null;
        $isFollowing = false;
        $extra = [];
        if ($convId = $request->get('conversation')) {
            $selected = Conversation::where('workspace_id', $user->workspace_id)
                ->where('id', $convId)
                ->with([
                    'customer',
                    'mailbox',
                    'assignedUser',
                    'tags',
                    'folders',
                    'followers:id,name,avatar',
                    'threads' => fn($q) => $q->with(['user', 'customer', 'attachments'])->orderBy('created_at'),
                ])
                ->first();

            if ($selected) {
                $isFollowing = $selected->followers->contains('id', $user->id);
                $extra = Hooks::applyFilters('conversation.show.extra', [], $selected);
            }
        }

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'mailboxes'     => $mailboxes,
            'tags'          => $tags,
            'folders'       => $folders,
            'counts'        => $counts,
            'agents'        => $agents,
            'selected'      => $selected,
            'isFollowing'   => $isFollowing,
            'filters'    => $request->only(['status', 'mailbox', 'assigned', 'tag', 'priority', 'conversation', 'date_from', 'date_to', 'view']),
            'activeView' => $activeView ? [
                'id'      => $activeView->id,
                'name'    => $activeView->name,
                'color'   => $activeView->color,
                'filters' => $activeView->filters,
            ] : null,
            ...$extra,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        // Auto-mark as read when the conversation is opened
        ConversationRead::markRead($request->user()->id, $conversation->id);

        $conversation->load([
            'customer',
            'mailbox',
            'assignedUser',
            'tags',
            'folders',
            'followers:id,name,avatar',
            'threads.user',
            'threads.customer',
            'threads.attachments',
            'aiSuggestions' => fn($q) => $q->where('type', 'reply')->latest()->limit(1),
        ]);

        $agents = \App\Models\User::where('workspace_id', $request->user()->workspace_id)
            ->get(['id', 'name', 'avatar']);

        $tags = Tag::where('workspace_id', $request->user()->workspace_id)->get();

        $folders = \App\Domains\Conversation\Models\Folder::where('workspace_id', $request->user()->workspace_id)
            ->orderBy('order')->get(['id', 'name', 'color', 'icon']);

        $mailboxes = \App\Domains\Mailbox\Models\Mailbox::where('workspace_id', $request->user()->workspace_id)
            ->where('active', true)->get(['id', 'name']);

        $extra = Hooks::applyFilters('conversation.show.extra', [], $conversation);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation,
            'agents'       => $agents,
            'tags'         => $tags,
            'folders'      => $folders,
            'convFolders'  => $conversation->folders->pluck('id'),
            'mailboxes'    => $mailboxes,
            'isFollowing'  => $conversation->followers->contains('id', $request->user()->id),
            ...$extra,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->workspace_id;

        $validated = $request->validate([
            'mailbox_id'  => ['required', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
            'subject'     => ['required', 'string', 'max:255'],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('workspace_id', $workspaceId)],
            'body'        => ['required', 'string'],
        ]);

        ['conversation' => $conversation] = $this->service->create($validated, $workspaceId, $request->user());

        return redirect()->route('conversations.show', $conversation);
    }

    public function updateStatus(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['status' => ['required', Rule::enum(ConversationStatus::class)]]);

        $this->service->changeStatus(
            $conversation,
            $request->enum('status', ConversationStatus::class),
            $request->user(),
        );

        return back();
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['user_id' => ['nullable', Rule::exists('users', 'id')->where('workspace_id', $request->user()->workspace_id)]]);

        $this->service->assign($conversation, $request->user_id, $request->user());

        return back();
    }

    public function snooze(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['until' => ['required', 'date', 'after:now']]);

        $this->service->snooze($conversation, \Carbon\Carbon::parse($request->until), $request->user());

        return back();
    }

    public function updatePriority(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['priority' => ['required', Rule::enum(ConversationPriority::class)]]);

        $this->service->changePriority(
            $conversation,
            $request->enum('priority', ConversationPriority::class),
            $request->user(),
        );

        return back();
    }

    public function merge(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['into_id' => ['required', Rule::exists('conversations', 'id')->where('workspace_id', $request->user()->workspace_id)]]);

        if ((int) $request->into_id === $conversation->id) {
            return back()->with('error', 'A conversation cannot be merged into itself.');
        }

        $target = Conversation::findOrFail($request->into_id);
        $this->authorize('update', $target);

        $this->service->merge($conversation, $target, $request->user());

        return redirect()->route('conversations.show', $target);
    }

    public function syncTags(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $workspaceId = $request->user()->workspace_id;
        $request->validate(['tag_ids' => ['array'], 'tag_ids.*' => [Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)]]);

        $conversation->tags()->sync($request->tag_ids ?? []);

        return back();
    }

    public function syncFolders(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $workspaceId = $request->user()->workspace_id;

        $request->validate([
            'folder_ids'   => ['array'],
            'folder_ids.*' => [Rule::exists('folders', 'id')->where('workspace_id', $workspaceId)],
        ]);

        $conversation->folders()->sync($request->folder_ids ?? []);

        return back();
    }

    public function changeMailbox(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $request->validate([
            'mailbox_id' => ['required', Rule::exists('mailboxes', 'id')->where('workspace_id', $request->user()->workspace_id)],
        ]);

        $this->service->changeMailbox($conversation, $request->mailbox_id, $request->user());

        return back();
    }

    private function folderCounts(\App\Models\User $user): array
    {
        // Single query with conditional aggregates — avoids 5 separate COUNT queries.
        $row = DB::table('conversations')
            ->where('workspace_id', $user->workspace_id)
            ->selectRaw("
                COUNT(CASE WHEN status = 'open'    AND snoozed_until IS NULL THEN 1 END) AS open,
                COUNT(CASE WHEN status = 'pending'                           THEN 1 END) AS pending,
                COUNT(CASE WHEN status = 'closed'                            THEN 1 END) AS closed,
                COUNT(CASE WHEN status = 'open'    AND snoozed_until IS NULL AND assigned_user_id = ? THEN 1 END) AS mine,
                COUNT(CASE WHEN snoozed_until IS NOT NULL AND snoozed_until > NOW() THEN 1 END) AS snoozed
            ", [$user->id])
            ->first();

        return [
            'open'    => (int) ($row->open    ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'closed'  => (int) ($row->closed  ?? 0),
            'mine'    => (int) ($row->mine    ?? 0),
            'snoozed' => (int) ($row->snoozed ?? 0),
        ];
    }

    // ── Read / Unread ─────────────────────────────────────────────────────────

    public function markRead(Request $request, Conversation $conversation): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $conversation);

        ConversationRead::markRead($request->user()->id, $conversation->id);

        return response()->json(['ok' => true]);
    }

    public function markUnread(Request $request, Conversation $conversation): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $conversation);

        ConversationRead::where('user_id', $request->user()->id)
            ->where('conversation_id', $conversation->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulk(Request $request): \Illuminate\Http\JsonResponse
    {
        $workspaceId = $request->user()->workspace_id;
        $actor       = $request->user();

        $validated = $request->validate([
            'ids'          => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'        => ['integer'],
            'action'       => ['required', 'in:close,reopen,assign,snooze,spam,priority,mark_read,mark_unread'],
            'assigned_to'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('workspace_id', $workspaceId)],
            'snooze_until' => ['nullable', 'date', 'after:now'],
            'priority'     => ['nullable', Rule::enum(ConversationPriority::class)],
        ]);

        // Scope all IDs to the workspace — prevents cross-workspace access
        $conversations = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($conversations as $conversation) {
            match ($validated['action']) {
                'close'      => $this->service->changeStatus($conversation, ConversationStatus::Closed, $actor),
                'reopen'     => $this->service->changeStatus($conversation, ConversationStatus::Open, $actor),
                'spam'       => $this->service->changeStatus($conversation, ConversationStatus::Spam, $actor),
                'assign'     => $this->service->assign($conversation, $validated['assigned_to'] ?: null, $actor),
                'snooze'     => $this->service->snooze($conversation, \Carbon\Carbon::parse($validated['snooze_until']), $actor, silent: true),
                'priority'   => $this->service->changePriority($conversation, ConversationPriority::from($validated['priority']), $actor, silent: true),
                'mark_read'  => ConversationRead::markRead($actor->id, $conversation->id),
                'mark_unread'=> ConversationRead::where('user_id', $actor->id)->where('conversation_id', $conversation->id)->delete(),
                default      => null,
            };
        }

        return response()->json(['updated' => $conversations->count()]);
    }
}
