<?php

namespace App\Http\Controllers\Conversations;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Folder;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Mailbox\Models\Mailbox;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Domains\Automation\Jobs\RunAutomationRulesJob;
use App\Notifications\ConversationAssignedNotification;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $request->validate([
            'status'   => 'nullable|in:open,pending,closed,spam,snoozed',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'mailbox'  => 'nullable|integer',
            'assigned' => 'nullable|in:me,none,all',
            'tag'      => 'nullable|integer',
            'folder'   => 'nullable|integer',
        ]);

        $query = Conversation::query()
            ->where('workspace_id', $user->workspace_id)
            ->with(['customer', 'mailbox', 'assignedUser', 'tags'])
            ->orderByDesc('last_reply_at');

        $status = $request->get('status', 'open');
        if ($status === 'snoozed') {
            $query->snoozed();
        } else {
            $query->where('status', $status)->whereNull('snoozed_until');
        }

        if ($mailboxId = $request->get('mailbox')) {
            $query->where('mailbox_id', $mailboxId);
        }

        match ($request->get('assigned')) {
            'me'   => $query->where('assigned_user_id', $user->id),
            'none' => $query->whereNull('assigned_user_id'),
            default => null,
        };

        if ($tagId = $request->get('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($folderId = $request->get('folder')) {
            $query->whereHas('folders', fn($q) => $q->where('folders.id', $folderId));
        }

        $conversations = $query->paginate(30)->withQueryString();
        $mailboxes     = Mailbox::where('workspace_id', $user->workspace_id)->get(['id', 'name', 'email']);
        $tags          = Tag::where('workspace_id', $user->workspace_id)->get(['id', 'name', 'color']);
        $folders       = Folder::where('workspace_id', $user->workspace_id)->orderBy('order')->get(['id', 'name', 'color', 'icon']);
        $counts        = $this->folderCounts($user);
        $agents        = \App\Models\User::where('workspace_id', $user->workspace_id)->get(['id', 'name']);

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
            'filters'       => $request->only(['status', 'mailbox', 'assigned', 'tag', 'priority', 'conversation']),
            ...$extra,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

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

        $conversation = DB::transaction(function () use ($workspaceId, $validated, $request) {
            $conversation = Conversation::create([
                'workspace_id'  => $workspaceId,
                'mailbox_id'    => $validated['mailbox_id'],
                'customer_id'   => $validated['customer_id'],
                'subject'       => $validated['subject'],
                'status'        => 'open',
                'channel_type'  => 'email',
                'last_reply_at' => now(),
            ]);

            $conversation->threads()->create([
                'user_id' => $request->user()->id,
                'type'    => 'message',
                'body'    => $validated['body'],
                'source'  => 'web',
            ]);

            return $conversation;
        });

        RunAutomationRulesJob::dispatch('conversation.created', $conversation);

        return redirect()->route('conversations.show', $conversation);
    }

    public function updateStatus(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['status' => ['required', 'in:open,pending,closed,spam']]);

        $oldStatus = $conversation->status;
        $newStatus = $request->status;
        $conversation->update(['status' => $newStatus]);

        $labels = ['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed', 'spam' => 'Spam'];
        $actor  = $request->user()->name;
        $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "{$actor} changed status from <strong>{$labels[$oldStatus]}</strong> to <strong>{$labels[$newStatus]}</strong>",
        ]);

        Hooks::doAction('conversation.updated', $conversation->fresh());
        broadcast(new ConversationUpdated($conversation));

        if ($newStatus === 'closed') {
            Hooks::doAction('conversation.closed', $conversation);
            RunAutomationRulesJob::dispatch('conversation.closed', $conversation);
        }

        return back();
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $workspaceId = $request->user()->workspace_id;
        $request->validate(['user_id' => ['nullable', Rule::exists('users', 'id')->where('workspace_id', $workspaceId)]]);

        $conversation->update(['assigned_user_id' => $request->user_id]);

        $actor = $request->user()->name;
        if ($request->user_id) {
            $assignee = \App\Models\User::find($request->user_id);
            $body = $assignee->id === $request->user()->id
                ? "{$actor} self-assigned this conversation"
                : "{$actor} assigned this conversation to <strong>{$assignee->name}</strong>";
        } else {
            $body = "{$actor} unassigned this conversation";
            $assignee = null;
        }

        $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => $body,
        ]);

        Hooks::doAction('conversation.updated', $conversation->fresh());
        broadcast(new ConversationUpdated($conversation));

        $assignee?->notify(new ConversationAssignedNotification($conversation->fresh()));

        return back();
    }

    public function snooze(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['until' => ['required', 'date', 'after:now']]);

        $conversation->update(['snoozed_until' => $request->until]);

        $until = \Carbon\Carbon::parse($request->until)->format('M j, Y g:i A');
        $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "{$request->user()->name} snoozed this conversation until <strong>{$until}</strong>",
        ]);

        return back();
    }

    public function updatePriority(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['priority' => ['required', 'in:low,normal,high,urgent']]);

        $oldPriority = $conversation->priority;
        $newPriority = $request->priority;
        $conversation->update(['priority' => $newPriority]);

        $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'source'  => 'web',
            'body'    => "{$request->user()->name} changed priority from <strong>" . ucfirst($oldPriority) . "</strong> to <strong>" . ucfirst($newPriority) . "</strong>",
        ]);

        Hooks::doAction('conversation.updated', $conversation->fresh());
        broadcast(new ConversationUpdated($conversation));

        return back();
    }

    public function merge(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);
        $request->validate(['into_id' => ['required', 'exists:conversations,id']]);

        if ((int) $request->into_id === $conversation->id) {
            return back()->with('error', 'A conversation cannot be merged into itself.');
        }

        $target = Conversation::findOrFail($request->into_id);
        $this->authorize('update', $target);

        $conversation->threads()->update(['conversation_id' => $target->id]);

        $target->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'body'    => "Conversation #{$conversation->id} was merged into this conversation.",
            'source'  => 'web',
        ]);

        $target->update(['last_reply_at' => now()]);
        $conversation->delete();

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
        $workspaceId = $request->user()->workspace_id;

        $request->validate([
            'mailbox_id' => ['required', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
        ]);

        $conversation->update(['mailbox_id' => $request->mailbox_id]);

        $conversation->refresh()->load('mailbox');

        $conversation->threads()->create([
            'user_id' => $request->user()->id,
            'type'    => 'activity',
            'body'    => "Conversation moved to mailbox: {$conversation->mailbox->name}",
            'source'  => 'web',
        ]);

        broadcast(new ConversationUpdated($conversation->fresh()));

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
}
