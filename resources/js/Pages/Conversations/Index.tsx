import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import ConversationInspector from '@/Components/conversations/ConversationInspector';
import { cn, getInitials, sanitizeHtml } from '@/lib/utils';
import type { Conversation, Mailbox, Tag, Thread, User, Customer, Attachment, Paginated, PageProps, Folder } from '@/types';
import { InboxIcon, PlusIcon, ClockIcon, SendIcon, StickyNoteIcon, XIcon, SearchIcon, PanelRightCloseIcon, PanelRightOpenIcon, ExternalLinkIcon, MailboxIcon, ChevronDownIcon, CheckSquareIcon, CheckIcon } from 'lucide-react';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import RichTextEditor from '@/Components/RichTextEditor';
import type { Editor } from '@tiptap/react';
import { useConversationListShortcuts } from '@/hooks/useConversationShortcuts';

// ── Canned response picker (Tiptap-aware) ─────────────────────────────────────

interface CannedResponse { id: number; name: string; content: string }

function useCannedResponsePicker(mailboxId?: number) {
    const [results, setResults]     = useState<CannedResponse[]>([]);
    const [activeIndex, setActiveIndex] = useState(0);
    const editorRef   = useRef<Editor | null>(null);
    const slashPosRef = useRef<number | null>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const onEditorReady = useCallback((editor: Editor) => {
        editorRef.current = editor;
    }, []);

    const onEditorUpdate = useCallback((editor: Editor) => {
        const { $from } = editor.state.selection;
        const textBefore = $from.parent.textContent.slice(0, $from.parentOffset);
        const match = textBefore.match(/(^|\s)\/([\w-]*)$/);

        if (match) {
            const query = match[2];
            const charsFromSlash = match[0].length - (match[1] ? match[1].length : 0);
            slashPosRef.current = $from.pos - charsFromSlash;
            setActiveIndex(0);
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(async () => {
                const mbParam = mailboxId ? `&mailbox_id=${mailboxId}` : '';
                const res = await fetch(`/canned-responses/search?q=${encodeURIComponent(query)}${mbParam}`);
                const data: CannedResponse[] = await res.json();
                setResults(data);
            }, 150);
        } else {
            setResults([]);
            slashPosRef.current = null;
        }
    }, []);

    const pickResponse = useCallback((r: CannedResponse) => {
        const editor = editorRef.current;
        if (!editor || slashPosRef.current === null) return;
        const to = editor.state.selection.from;
        editor.chain()
            .deleteRange({ from: slashPosRef.current, to })
            .insertContent(r.content)
            .focus()
            .run();
        setResults([]);
        slashPosRef.current = null;
    }, []);

    const onKeyDown = useCallback((e: KeyboardEvent) => {
        if (!results.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActiveIndex(i => (i + 1) % results.length); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); setActiveIndex(i => (i - 1 + results.length) % results.length); }
        if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pickResponse(results[activeIndex]); }
        if (e.key === 'Escape')    { setResults([]); slashPosRef.current = null; }
    }, [results, activeIndex, pickResponse]);

    return { results, activeIndex, onEditorReady, onEditorUpdate, pickResponse, onKeyDown };
}

function CannedDropdown({ results, activeIndex, onPick }: {
    results: CannedResponse[];
    activeIndex: number;
    onPick: (r: CannedResponse) => void;
}) {
    if (!results.length) return null;
    return (
        <div className="absolute bottom-full left-0 right-0 mb-1 bg-popover border border-border rounded-lg shadow-lg overflow-hidden z-50">
            {results.map((r, i) => (
                <button
                    key={r.id}
                    type="button"
                    onMouseDown={(e) => { e.preventDefault(); onPick(r); }}
                    className={cn(
                        'w-full text-left px-3 py-2 flex items-start gap-3 hover:bg-muted/60 transition-colors',
                        i === activeIndex && 'bg-muted/60',
                    )}
                >
                    <span className="inline-flex items-center rounded bg-primary/10 text-primary text-xs font-mono px-1.5 py-0.5 shrink-0 mt-0.5">
                        /{r.name}
                    </span>
                    <span className="text-xs text-muted-foreground line-clamp-2">{r.content}</span>
                </button>
            ))}
        </div>
    );
}

// ── Extended types ────────────────────────────────────────────────────────────

interface FullThread extends Thread {
    user?: User;
    customer?: Customer;
    attachments?: Attachment[];
}

interface FullConversation extends Conversation {
    threads: FullThread[];
    customer: Customer;
    mailbox: Mailbox;
    assigned_user?: User;
    tags: Tag[];
    folders?: Folder[];
    followers?: Pick<User, 'id' | 'name' | 'avatar'>[];
}

interface Props {
    conversations: Paginated<Conversation>;
    mailboxes: Mailbox[];
    tags: Tag[];
    folders: Folder[];
    counts: Record<string, number>;
    filters: {
        status?: string;
        mailbox?: string;
        assigned?: string;
        tag?: string;
        priority?: string;
        conversation?: string;
    };
    selected?: FullConversation | null;
    agents: { id: number; name: string }[];
    isFollowing: boolean;
    survey?: {
        rating: 'good' | 'bad';
        responded_at: string;
    } | null;
}

// ── Helpers ───────────────────────────────────────────────────────────────────


function relativeTime(dateStr?: string): string {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}

function buildConversationLink(conversationId: number): string {
    return `/conversations/${conversationId}`;
}

// ── New conversation modal ────────────────────────────────────────────────────

interface CustomerOption { id: number; name: string; email: string }

function NewConversationModal({ mailboxes, onClose }: { mailboxes: Mailbox[]; onClose: () => void }) {
    const [mailboxId, setMailboxId] = useState(mailboxes[0]?.id ?? '');
    const [subject, setSubject]     = useState('');
    const [body, setBody]           = useState('');
    const [submitting, setSubmitting] = useState(false);

    const [customerQuery, setCustomerQuery]   = useState('');
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>([]);
    const [selectedCustomer, setSelectedCustomer] = useState<CustomerOption | null>(null);
    const [showDropdown, setShowDropdown]     = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    function searchCustomers(q: string) {
        setCustomerQuery(q);
        setSelectedCustomer(null);
        if (!q.trim()) { setCustomerOptions([]); setShowDropdown(false); return; }
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            const res = await fetch(`/customers/search?q=${encodeURIComponent(q)}`);
            const data: CustomerOption[] = await res.json();
            setCustomerOptions(data);
            setShowDropdown(true);
        }, 200);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedCustomer || !mailboxId || !subject.trim() || !body.trim()) return;
        setSubmitting(true);
        router.post('/conversations', {
            mailbox_id: mailboxId,
            subject,
            customer_id: selectedCustomer.id,
            body,
        }, {
            onSuccess: () => onClose(),
            onError: () => setSubmitting(false),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/40" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="bg-card rounded-xl shadow-2xl border border-border w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
                    <h2 className="font-semibold text-base">New Conversation</h2>
                    <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground">
                        <XIcon className="h-4 w-4" />
                    </button>
                </div>

                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0 overflow-y-auto">
                    <div className="px-5 py-4 space-y-4">
                        {/* Mailbox */}
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Mailbox</label>
                            <select
                                className="w-full border border-input rounded-md px-3 py-2 text-sm bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                value={mailboxId}
                                onChange={e => setMailboxId(Number(e.target.value))}
                                required
                            >
                                {mailboxes.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </div>

                        {/* Customer */}
                        <div className="space-y-1 relative">
                            <label className="text-xs font-medium text-muted-foreground">Customer</label>
                            {selectedCustomer ? (
                                <div className="flex items-center justify-between border border-input rounded-md px-3 py-2 bg-muted/30">
                                    <div>
                                        <span className="text-sm font-medium">{selectedCustomer.name}</span>
                                        <span className="text-xs text-muted-foreground ml-2">{selectedCustomer.email}</span>
                                    </div>
                                    <button type="button" onClick={() => { setSelectedCustomer(null); setCustomerQuery(''); }} className="text-muted-foreground hover:text-foreground ml-2">
                                        <XIcon className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ) : (
                                <div className="relative">
                                    <SearchIcon className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                    <input
                                        className="w-full border border-input rounded-md pl-8 pr-3 py-2 text-sm bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                        placeholder="Search by name or email…"
                                        value={customerQuery}
                                        onChange={e => searchCustomers(e.target.value)}
                                        onBlur={() => setTimeout(() => setShowDropdown(false), 150)}
                                    />
                                    {showDropdown && customerOptions.length > 0 && (
                                        <div className="absolute top-full left-0 right-0 mt-1 bg-popover border border-border rounded-lg shadow-lg z-10 overflow-hidden">
                                            {customerOptions.map(c => (
                                                <button
                                                    key={c.id}
                                                    type="button"
                                                    onMouseDown={() => { setSelectedCustomer(c); setShowDropdown(false); }}
                                                    className="w-full text-left px-3 py-2 hover:bg-muted/60 transition-colors"
                                                >
                                                    <span className="text-sm font-medium">{c.name}</span>
                                                    <span className="text-xs text-muted-foreground ml-2">{c.email}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    {showDropdown && customerOptions.length === 0 && customerQuery.trim() && (
                                        <div className="absolute top-full left-0 right-0 mt-1 bg-popover border border-border rounded-lg shadow-lg z-10 px-3 py-2 text-sm text-muted-foreground">
                                            No customers found
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Subject */}
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Subject</label>
                            <input
                                className="w-full border border-input rounded-md px-3 py-2 text-sm bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="e.g. Question about billing"
                                value={subject}
                                onChange={e => setSubject(e.target.value)}
                                required
                            />
                        </div>

                        {/* Message */}
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Message</label>
                            <RichTextEditor
                                value={body}
                                onChange={setBody}
                                placeholder="Write your message…"
                                minHeight="120px"
                            />
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-2 px-5 py-4 border-t border-border shrink-0">
                        <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={submitting || !selectedCustomer || !subject.trim() || !body.trim() || body === '<p></p>'}
                        >
                            {submitting ? 'Creating…' : 'Create Conversation'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function ConversationsIndex({ conversations, mailboxes, tags, folders, counts, filters, selected, agents, isFollowing, survey }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isAgent = auth.user?.role === 'agent';
    const activeStatus = filters.status ?? 'open';
    const [showNewConv, setShowNewConv] = useState(false);
    const [mobileShowDetail, setMobileShowDetail] = useState(!!selected);
    const appliedAgentDefaultRef = useRef(false);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkLoading, setBulkLoading] = useState(false);

    const allIds = conversations.data.map(c => c.id);
    const allSelected = allIds.length > 0 && allIds.every(id => selectedIds.has(id));
    const currentSelectedId = selected?.id ?? null;

    useConversationListShortcuts({
        conversationIds: allIds,
        currentId: currentSelectedId,
        onSelect: (id) => {
            const conv = conversations.data.find(c => c.id === id);
            if (conv) selectConversation(conv);
        },
    });

    function toggleSelect(id: number, e: React.MouseEvent) {
        e.stopPropagation();
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function toggleSelectAll() {
        setSelectedIds(allSelected ? new Set() : new Set(allIds));
    }

    async function bulkAction(action: string, extra: Record<string, unknown> = {}) {
        if (selectedIds.size === 0) return;
        setBulkLoading(true);
        try {
            await fetch('/conversations/bulk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids: [...selectedIds], action, ...extra }),
            });
            setSelectedIds(new Set());
            router.reload({ only: ['conversations', 'counts'] });
        } finally {
            setBulkLoading(false);
        }
    }

    function go(params: Record<string, string | undefined>) {
        router.get('/conversations', { ...filters, ...params }, { preserveScroll: true, replace: true });
    }

    function selectConversation(conv: Conversation) {
        setMobileShowDetail(true);
        router.get(
            '/conversations',
            { ...filters, conversation: String(conv.id) },
            { preserveState: true, preserveScroll: true, replace: true, only: ['selected'] },
        );
    }

    // Agent default: open list scoped to self unless an explicit assignment filter is provided.
    useEffect(() => {
        if (!isAgent || appliedAgentDefaultRef.current || filters.assigned !== undefined) return;

        appliedAgentDefaultRef.current = true;
        router.get(
            '/conversations',
            { ...filters, assigned: 'me' },
            { preserveScroll: true, replace: true },
        );
    }, [isAgent, filters]);

    // Real-time: reload list when a conversation is updated in this workspace
    useEffect(() => {
        const ch = window.Echo?.private(`workspace.${auth.user?.workspace_id}`);
        ch?.listen('.conversation.updated', () => {
            router.reload({ only: ['conversations', 'counts'] });
        });
        return () => ch?.stopListening('.conversation.updated');
    }, [auth.user?.workspace_id]);

    const statusTabs = [
        { key: 'open',    label: 'Open',    count: counts.open },
        { key: 'pending', label: 'Pending', count: counts.pending },
        { key: 'snoozed', label: 'Snoozed', count: counts.snoozed },
        { key: 'closed',  label: 'Closed' },
    ];
    const activeTag = tags.find((tag) => String(tag.id) === filters.tag);

    return (
        <AppLayout fullHeight>
            <Head title="Conversations" />

            <div className="flex flex-1 min-h-0 overflow-hidden">
                {/* ── Panel 1: Conversation List ── */}
                <div className={cn(
                    'shrink-0 flex flex-col border-r border-border bg-background overflow-hidden',
                    // Mobile: full width, hide when viewing detail
                    'w-full md:w-[20rem] xl:w-[21rem]',
                    mobileShowDetail ? 'hidden md:flex' : 'flex',
                )}>
                    {/* Header */}
                    <div className="flex items-center justify-between px-4 pt-4 pb-3 shrink-0">
                        <h1 className="font-semibold text-lg tracking-tight">Inbox</h1>
                        <Button
                            size="sm"
                            className="h-8 gap-1.5 text-xs rounded-lg px-2.5"
                            onClick={() => setShowNewConv(true)}
                        >
                            <PlusIcon className="h-3.5 w-3.5" />
                            New
                        </Button>
                    </div>

                    {/* Status tabs — pill style */}
                    <div className="flex items-center gap-1 px-3 pb-3 border-b border-border shrink-0">
                        {statusTabs.map(({ key, label, count }) => (
                            <button
                                key={key}
                                onClick={() => go({ status: key, page: undefined })}
                                className={cn(
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                                    activeStatus === key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/60',
                                )}
                            >
                                {label}
                                {count !== undefined && count > 0 && (
                                    <span className={cn(
                                        'ml-1.5 text-[10px] tabular-nums',
                                        activeStatus === key ? 'opacity-75' : 'opacity-55',
                                    )}>
                                        {count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {/* Filters row */}
                    <div className="px-3 py-2 border-b border-border shrink-0 flex items-center gap-2">
                        {/* Assignment segmented control */}
                        <div className="flex items-center bg-muted/60 rounded-lg p-0.5 gap-0.5">
                            {[
                                { value: 'all',  label: 'All' },
                                { value: 'me',   label: 'Mine' },
                                { value: 'none', label: 'Unassigned' },
                            ].map(({ value, label }) => {
                                const current = filters.assigned ?? (isAgent ? 'me' : 'all');
                                return (
                                    <button
                                        key={value}
                                        onClick={() => go({ assigned: value === 'all' ? undefined : value, page: undefined })}
                                        className={cn(
                                            'rounded-md px-2.5 py-1 text-[11px] font-medium transition-all whitespace-nowrap',
                                            current === value
                                                ? 'bg-background text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {label}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Mailbox filter — popover */}
                        {mailboxes.length > 1 && (() => {
                            const activeMb = mailboxes.find(mb => filters.mailbox === String(mb.id));
                            return (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button className={cn(
                                            'ml-auto flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all border',
                                            activeMb
                                                ? 'border-primary/30 bg-primary/5 text-primary'
                                                : 'border-border bg-background text-muted-foreground hover:text-foreground',
                                        )}>
                                            <MailboxIcon className="h-3 w-3" />
                                            <span>{activeMb ? activeMb.name : 'All'}</span>
                                            <ChevronDownIcon className="h-2.5 w-2.5 opacity-60" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-44">
                                        <DropdownMenuItem onClick={() => go({ mailbox: undefined, page: undefined })}>
                                            <span className={cn(!activeMb && 'text-primary font-medium')}>All mailboxes</span>
                                        </DropdownMenuItem>
                                        {mailboxes.map((mb) => (
                                            <DropdownMenuItem key={mb.id} onClick={() => go({ mailbox: String(mb.id), page: undefined })}>
                                                <span className={cn(filters.mailbox === String(mb.id) && 'text-primary font-medium')}>{mb.name}</span>
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            );
                        })()}
                    </div>

                    {/* Active tag indicator (compact) */}
                    {activeTag && (
                        <div className="flex items-center gap-2 px-3 py-1.5 border-b border-border shrink-0">
                            <span className="text-xs text-muted-foreground">Tag filter:</span>
                            <span
                                className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                style={{ backgroundColor: activeTag.color + '30', color: activeTag.color }}
                            >
                                {activeTag.name}
                            </span>
                            <button
                                type="button"
                                onClick={() => go({ tag: undefined, page: undefined })}
                                className="text-xs text-muted-foreground hover:text-foreground"
                            >
                                Clear
                            </button>
                        </div>
                    )}

                    {/* Bulk action bar — floats above list when items selected */}
                    {selectedIds.size > 0 && (
                        <div className="flex items-center gap-2 px-3 py-2 bg-primary/5 border-b border-primary/20 shrink-0 flex-wrap">
                            <span className="text-xs font-medium text-primary">{selectedIds.size} selected</span>
                            <div className="h-3 w-px bg-border mx-1" />
                            <button
                                type="button"
                                onClick={() => bulkAction('close')}
                                disabled={bulkLoading}
                                className="text-xs px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted transition-colors disabled:opacity-50"
                            >
                                Close
                            </button>
                            <button
                                type="button"
                                onClick={() => bulkAction('reopen')}
                                disabled={bulkLoading}
                                className="text-xs px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted transition-colors disabled:opacity-50"
                            >
                                Reopen
                            </button>
                            <button
                                type="button"
                                onClick={() => bulkAction('assign', { assigned_to: auth.user.id })}
                                disabled={bulkLoading}
                                className="text-xs px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted transition-colors disabled:opacity-50"
                            >
                                Assign to me
                            </button>
                            <button
                                type="button"
                                onClick={() => bulkAction('spam')}
                                disabled={bulkLoading}
                                className="text-xs px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted text-destructive hover:text-destructive transition-colors disabled:opacity-50"
                            >
                                Mark spam
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedIds(new Set())}
                                className="ml-auto text-xs text-muted-foreground hover:text-foreground"
                            >
                                <XIcon className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}

                    {/* Select-all header row */}
                    {conversations.data.length > 0 && (
                        <div className="flex items-center gap-2.5 px-3.5 py-2 border-b border-border/60 bg-muted/20 shrink-0">
                            <Checkbox
                                checked={allSelected}
                                onCheckedChange={toggleSelectAll}
                                className="h-3.5 w-3.5"
                                aria-label="Select all"
                            />
                            <span className="text-[11px] text-muted-foreground">
                                {allSelected ? 'Deselect all' : 'Select all'}
                            </span>
                        </div>
                    )}

                    {/* List */}
                    <div className="flex-1 overflow-y-auto divide-y divide-border/60">
                        {conversations.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 px-6 text-center gap-2">
                                <div className="w-12 h-12 rounded-2xl bg-muted/60 flex items-center justify-center mb-1">
                                    <InboxIcon className="h-5 w-5 text-muted-foreground/60" />
                                </div>
                                <p className="text-sm font-medium text-foreground/60">All clear</p>
                                <p className="text-xs text-muted-foreground/60 leading-relaxed max-w-[14rem]">
                                    No {activeStatus} conversations match your current filters.
                                </p>
                            </div>
                        ) : (
                            conversations.data.map((conv) => (
                                <ConversationRow
                                    key={conv.id}
                                    conversation={conv}
                                    isSelected={selected?.id === conv.id}
                                    isChecked={selectedIds.has(conv.id)}
                                    onCheck={(e) => toggleSelect(conv.id, e)}
                                    onClick={() => selectConversation(conv)}
                                />
                            ))
                        )}
                    </div>

                    {/* Pagination */}
                    {conversations.last_page > 1 && (
                        <div className="px-3 py-2 border-t border-border flex justify-between text-xs text-muted-foreground shrink-0">
                            <span>{conversations.from}–{conversations.to} of {conversations.total}</span>
                            <div className="flex gap-2">
                                {conversations.current_page > 1 && (
                                    <button onClick={() => go({ page: String(conversations.current_page - 1) })} className="hover:text-foreground">Prev</button>
                                )}
                                {conversations.current_page < conversations.last_page && (
                                    <button onClick={() => go({ page: String(conversations.current_page + 1) })} className="hover:text-foreground">Next</button>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* ── Panel 2: Conversation Detail ── */}
                <div className={cn(
                    'flex-1 min-w-0 overflow-hidden flex flex-col',
                    // Mobile: only show when a conversation is selected
                    mobileShowDetail ? 'flex' : 'hidden md:flex',
                )}>
                    {/* Mobile back button */}
                    {mobileShowDetail && (
                        <div className="flex md:hidden items-center border-b border-border px-3 py-2 shrink-0 bg-background">
                            <button
                                type="button"
                                onClick={() => setMobileShowDetail(false)}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                Back to inbox
                            </button>
                        </div>
                    )}
                    {!selected ? (
                        <div className="flex h-full items-center justify-center bg-muted/10 relative">
                            {/* Subtle background pattern */}
                            <div className="pointer-events-none absolute inset-0"
                                style={{
                                    backgroundImage: 'radial-gradient(circle, var(--border) 1px, transparent 1px)',
                                    backgroundSize: '24px 24px',
                                    opacity: 0.45,
                                }} />
                            <div className="relative text-center px-8">
                                <div className="w-14 h-14 rounded-2xl bg-background border border-border shadow-sm flex items-center justify-center mx-auto mb-4">
                                    <InboxIcon className="h-6 w-6 text-muted-foreground/50" />
                                </div>
                                <p className="text-sm font-medium text-foreground/60 mb-1">No conversation selected</p>
                                <p className="text-xs text-muted-foreground/50">Pick one from the list to get started</p>
                            </div>
                        </div>
                    ) : selected.channel_type === 'chat' ? (
                        <ChatDetail conversation={selected} />
                    ) : (
                        <EmailDetail
                            conversation={selected}
                            agents={agents}
                            tags={tags}
                            folders={folders}
                            isFollowing={isFollowing}
                            survey={survey}
                        />
                    )}
                </div>
            </div>
            {showNewConv && (
                <NewConversationModal mailboxes={mailboxes} onClose={() => setShowNewConv(false)} />
            )}
        </AppLayout>
    );
}

// ── Conversation row ──────────────────────────────────────────────────────────

const priorityBar: Record<string, string> = {
    urgent: 'bg-destructive',
    high:   'bg-warning',
    normal: 'bg-transparent',
    low:    'bg-transparent',
};

function ConversationRow({
    conversation,
    isSelected,
    isChecked,
    onCheck,
    onClick,
}: {
    conversation: Conversation;
    isSelected: boolean;
    isChecked: boolean;
    onCheck: (e: React.MouseEvent) => void;
    onClick: () => void;
}) {
    const isSnoozed = conversation.snoozed_until && new Date(conversation.snoozed_until) > new Date();

    return (
        <div
            className={cn(
                'group w-full text-left flex items-stretch transition-colors',
                isSelected ? 'bg-primary/[0.07]' : 'hover:bg-muted/40',
            )}
        >
            {/* Priority accent bar */}
            <div className={cn(
                'w-[3px] shrink-0 transition-colors',
                isSelected ? 'bg-primary' : (priorityBar[conversation.priority] ?? 'bg-transparent'),
            )} />

            {/* Checkbox — visible on hover or when checked */}
            <div
                className="flex items-center pl-2 pr-0 py-3.5"
                onClick={onCheck}
            >
                <Checkbox
                    checked={isChecked}
                    className={cn(
                        'h-3.5 w-3.5 transition-opacity',
                        isChecked ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
                    )}
                    aria-label="Select conversation"
                />
            </div>

            <button
                type="button"
                onClick={onClick}
                className="flex-1 min-w-0 text-left"
            >
            <div className="flex-1 px-3 py-3.5 min-w-0">
                <div className="flex items-start gap-2.5">
                    <Avatar className="size-8 shrink-0 mt-0.5">
                        <AvatarImage src={conversation.customer?.avatar ?? undefined} alt={conversation.customer?.name ?? ''} />
                        <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                            {getInitials(conversation.customer?.name)}
                        </AvatarFallback>
                    </Avatar>

                    <div className="flex-1 min-w-0">
                        {/* Name + time */}
                        <div className="flex items-baseline justify-between gap-1 mb-0.5">
                            <span className="text-[13px] font-semibold truncate text-foreground/90 leading-tight">
                                {conversation.customer?.name ?? 'Unknown'}
                            </span>
                            <span className="text-[11px] text-muted-foreground/60 shrink-0 tabular-nums">
                                {relativeTime(conversation.last_reply_at ?? conversation.created_at)}
                            </span>
                        </div>

                        {/* Subject */}
                        <p className="text-[12.5px] text-muted-foreground truncate mb-2 leading-snug">
                            {conversation.subject}
                        </p>

                        {/* Metadata row */}
                        <div className="flex items-center gap-1.5 flex-wrap">
                            {isSnoozed && (
                                <span className="inline-flex items-center gap-0.5 text-[11px] text-warning font-medium px-1.5 py-0.5 rounded-md bg-warning/10">
                                    <ClockIcon className="h-2.5 w-2.5" />
                                    Snoozed
                                </span>
                            )}

                            {(conversation.tags ?? []).slice(0, 2).map((tag) => (
                                <span
                                    key={tag.id}
                                    className="text-[11px] px-1.5 py-0.5 rounded-md font-medium"
                                    style={{ backgroundColor: tag.color + '1f', color: tag.color }}
                                >
                                    {tag.name}
                                </span>
                            ))}

                            {conversation.assigned_user && (
                                <div className="ml-auto flex items-center gap-1">
                                    <Avatar className="size-[18px]">
                                        <AvatarImage src={conversation.assigned_user.avatar ?? undefined} alt={conversation.assigned_user.name} />
                                        <AvatarFallback className="bg-muted text-muted-foreground text-[9px] font-semibold">
                                            {getInitials(conversation.assigned_user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            </button>
        </div>
    );
}

// ── Email detail view ─────────────────────────────────────────────────────────

function EmailDetail({
    conversation,
    agents,
    tags,
    folders,
    isFollowing,
    survey,
}: {
    conversation: FullConversation;
    agents: { id: number; name: string }[];
    tags: Tag[];
    folders: Folder[];
    isFollowing: boolean;
    survey?: {
        rating: 'good' | 'bad';
        responded_at: string;
    } | null;
}) {
    const [replyType, setReplyType] = useState<'message' | 'note'>('message');
    const [replyExpanded, setReplyExpanded] = useState(false);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [inspectorOpen, setInspectorOpen] = useState(() => {
        if (typeof window === 'undefined') return true;
        return window.localStorage.getItem('conversation.inspector.open') !== '0';
    });
    const { results: cannedResults, activeIndex: cannedIndex, onEditorReady, onEditorUpdate, pickResponse, onKeyDown: cannedKeyDown } = useCannedResponsePicker(conversation.mailbox_id ?? undefined);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem('conversation.inspector.open', inspectorOpen ? '1' : '0');
    }, [inspectorOpen]);

    function sendReply(e: React.FormEvent) {
        e.preventDefault();
        if (!body.trim()) return;
        setSending(true);
        router.post(
            `/conversations/${conversation.id}/threads`,
            { body, type: replyType },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selected'],
                onSuccess: () => {
                    setBody('');
                    setSending(false);
                    setReplyExpanded(false);
                },
                onError: () => setSending(false),
            },
        );
    }

    function updateStatus(status: string) {
        router.patch(
            `/conversations/${conversation.id}/status`,
            { status },
            { preserveState: true, preserveScroll: true, only: ['selected', 'conversations', 'counts'] },
        );
    }

    function updatePriority(priority: string) {
        router.patch(
            `/conversations/${conversation.id}/priority`,
            { priority },
            { preserveState: true, preserveScroll: true, only: ['selected', 'conversations', 'counts'] },
        );
    }

    function assignTo(userId: string) {
        router.patch(
            `/conversations/${conversation.id}/assign`,
            { user_id: userId || null },
            { preserveState: true, preserveScroll: true, only: ['selected', 'conversations', 'counts', 'isFollowing', 'survey'] },
        );
    }

    function addTag(tagId: number) {
        const current = conversation.tags?.map((tag) => tag.id) ?? [];
        if (current.includes(tagId)) return;

        router.post(
            `/conversations/${conversation.id}/tags`,
            { tag_ids: [...current, tagId] },
            { preserveState: true, preserveScroll: true, only: ['selected', 'isFollowing', 'survey'] },
        );
    }

    function syncFolders(folderIds: number[]) {
        router.post(
            `/conversations/${conversation.id}/folders`,
            { folder_ids: folderIds },
            { preserveState: true, preserveScroll: true, only: ['selected', 'isFollowing', 'survey'] },
        );
    }

    function toggleFollow() {
        if (isFollowing) {
            router.delete(`/conversations/${conversation.id}/follow`, {
                preserveState: true,
                preserveScroll: true,
                only: ['selected', 'isFollowing'],
            });
            return;
        }

        router.post(
            `/conversations/${conversation.id}/follow`,
            {},
            { preserveState: true, preserveScroll: true, only: ['selected', 'isFollowing'] },
        );
    }

    const threads = (conversation.threads ?? []).filter((t) => t.type !== 'ai_suggestion');
    const conversationLink = buildConversationLink(conversation.id);

    return (
        <div className="flex flex-1 min-h-0 overflow-hidden">
            {/* ── Thread + reply area ── */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden min-h-0">
                {/* Header */}
                <div className="flex items-start gap-3 px-5 py-3 border-b border-border bg-background shrink-0">
                    <div className="flex-1 min-w-0 pt-0.5">
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] font-mono text-muted-foreground/50 bg-muted/60 px-1.5 py-0.5 rounded shrink-0">
                                #{conversation.id}
                            </span>
                            <h2 className="text-sm font-semibold leading-snug line-clamp-2">{conversation.subject}</h2>
                        </div>
                        <div className="flex items-center gap-2 mt-1">
                            <span className="text-xs text-muted-foreground">{conversation.mailbox?.name}</span>
                        </div>
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0 mt-0.5">
                        {conversation.status !== 'closed' && (
                            <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('closed')}>
                                Close
                            </Button>
                        )}
                        {conversation.status !== 'open' && (
                            <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('open')}>
                                Reopen
                            </Button>
                        )}
                        {conversation.status !== 'pending' && (
                            <Button size="sm" variant="ghost" className="h-8 text-xs text-muted-foreground hover:text-foreground" onClick={() => updateStatus('pending')}>
                                <ClockIcon className="h-3.5 w-3.5 mr-1" /> Pending
                            </Button>
                        )}
                        <button
                            type="button"
                            onClick={() => setInspectorOpen((v) => !v)}
                            title={inspectorOpen ? 'Hide details panel' : 'Show details panel'}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors"
                        >
                            {inspectorOpen ? <PanelRightCloseIcon className="h-4 w-4" /> : <PanelRightOpenIcon className="h-4 w-4" />}
                        </button>
                        <a
                            href={conversationLink}
                            target="_blank"
                            rel="noreferrer"
                            title="Open conversation in new tab"
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors"
                        >
                            <ExternalLinkIcon className="h-4 w-4" />
                        </a>
                    </div>
                </div>

                {/* Thread list */}
                <div className="flex-1 overflow-y-auto bg-muted/20">
                    <div className="w-full px-5 py-6 space-y-5">
                        {threads.map((thread) => (
                            <EmailThreadItem key={thread.id} thread={thread} />
                        ))}
                    </div>
                </div>

                {/* Reply editor */}
                <div className="bg-background shrink-0 px-4 pb-4 pt-3">
                    <div className={cn(
                        'border rounded-xl overflow-hidden transition-colors',
                        replyType === 'note' ? 'border-warning/40' : 'border-border',
                    )}>
                        {/* Tabs row — clicking a tab expands the editor */}
                        <div className="flex items-center gap-1 px-3 pt-2.5 pb-1">
                            {(['message', 'note'] as const).map((t) => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => { setReplyType(t); setReplyExpanded(true); }}
                                    className={cn(
                                        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-all',
                                        replyType === t && replyExpanded
                                            ? t === 'message'
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-warning/10 text-warning'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50',
                                    )}
                                >
                                    {t === 'message'
                                        ? <><SendIcon className="h-3 w-3" /> Reply</>
                                        : <><StickyNoteIcon className="h-3 w-3" /> Note</>
                                    }
                                </button>
                            ))}
                            {replyExpanded && (
                                <button
                                    type="button"
                                    onClick={() => { setReplyExpanded(false); setBody(''); }}
                                    className="ml-auto text-muted-foreground hover:text-foreground transition-colors p-1 rounded"
                                >
                                    <XIcon className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>

                        {replyExpanded ? (
                            <form onSubmit={sendReply}>
                                <div className="relative">
                                    <CannedDropdown results={cannedResults} activeIndex={cannedIndex} onPick={pickResponse} />
                                    <RichTextEditor
                                        value={body}
                                        onChange={(html) => setBody(html)}
                                        placeholder={replyType === 'message' ? 'Write your reply… (type / for canned responses)' : 'Write an internal note…'}
                                        minHeight="140px"
                                        className={cn(
                                            'border-0 border-t border-border rounded-none shadow-none',
                                            replyType === 'note' ? 'bg-warning/5 border-warning/30' : '',
                                        )}
                                        onEditorReady={(editor) => {
                                            onEditorReady(editor);
                                            editor.on('update', () => onEditorUpdate(editor));
                                        }}
                                        onKeyDown={cannedKeyDown}
                                    />
                                </div>
                                <div className="flex items-center justify-end px-3 py-2.5 border-t border-border bg-muted/20">
                                    <Button
                                        type="submit"
                                        disabled={sending || !body.trim() || body === '<p></p>'}
                                        className="gap-1.5 px-5"
                                    >
                                        <SendIcon className="h-3.5 w-3.5" />
                                        {replyType === 'message' ? 'Send Reply' : 'Add Note'}
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setReplyExpanded(true)}
                                className="w-full text-left px-4 py-3 text-sm text-muted-foreground/60 hover:text-muted-foreground transition-colors border-t border-border"
                            >
                                {replyType === 'message' ? 'Write your reply…' : 'Write an internal note…'}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Right sidebar ── */}
            <div className={cn(
                'shrink-0 bg-background transition-all duration-200',
                inspectorOpen ? 'w-[17rem] border-l border-border flex' : 'w-0 overflow-hidden border-l-0',
            )}>
                {inspectorOpen && (
                    <ConversationInspector
                        className="w-[17rem]"
                        conversation={conversation}
                        agents={agents}
                        tags={tags}
                        folders={folders}
                        selectedFolderIds={(conversation.folders ?? []).map((folder) => folder.id)}
                        isFollowing={isFollowing}
                        survey={survey}
                        onStatusChange={updateStatus}
                        onPriorityChange={updatePriority}
                        onAssignChange={assignTo}
                        onAddTag={addTag}
                        onAddFolder={(folderId) => syncFolders([...(conversation.folders ?? []).map((folder) => folder.id), folderId])}
                        onRemoveFolder={(folderId) => syncFolders((conversation.folders ?? []).map((folder) => folder.id).filter((id) => id !== folderId))}
                        onToggleFollow={toggleFollow}
                    />
                )}
            </div>
        </div>
    );
}

// ── Email thread item ─────────────────────────────────────────────────────────

function EmailThreadItem({ thread }: { thread: FullThread }) {
    const isFromCustomer = !!thread.customer_id;
    const isNote = thread.type === 'note';
    const isActivity = thread.type === 'activity';
    const author = isFromCustomer ? thread.customer : thread.user;

    if (isActivity) {
        return (
            <div className="flex items-center gap-3 py-1.5">
                <div className="flex-1 h-px bg-border/50" />
                <span
                    className="text-[11px] text-muted-foreground/60 shrink-0 px-1"
                    dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }}
                />
                <div className="flex-1 h-px bg-border/50" />
            </div>
        );
    }

    return (
        <div className={cn(
            'rounded-2xl text-sm overflow-hidden',
            isNote
                ? 'border border-amber-200/60 bg-amber-50/60 dark:border-amber-800/30 dark:bg-amber-950/20'
                : isFromCustomer
                    ? 'border border-border/70 bg-card shadow-sm'
                    : 'border-l-[3px] border border-primary/20 border-l-primary/50 bg-primary/[0.025] shadow-sm',
        )}>
            {/* Header */}
            <div className="flex items-center justify-between px-5 pt-4 pb-3">
                <div className="flex items-center gap-3 min-w-0">
                    <Avatar className="size-8 shrink-0">
                        <AvatarImage src={author?.avatar ?? undefined} alt={author?.name ?? 'Unknown'} />
                        <AvatarFallback className={cn(
                            'text-xs font-bold',
                            isFromCustomer ? 'bg-muted text-foreground/70' : 'bg-primary/15 text-primary',
                        )}>
                            {getInitials(author?.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <span className="font-semibold text-[13px] text-foreground">{author?.name ?? 'Unknown'}</span>
                        {isFromCustomer && (thread.customer as any)?.email && (
                            <span className="text-[11px] text-muted-foreground/70 ml-2">
                                {(thread.customer as any).email}
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0 ml-2">
                    {isNote && (
                        <span className="text-[10px] bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 px-1.5 py-0.5 rounded-full font-semibold tracking-wide uppercase">
                            Note
                        </span>
                    )}
                    {!isFromCustomer && !isNote && (
                        <span className="text-[10px] bg-primary/8 text-primary px-1.5 py-0.5 rounded-full font-semibold tracking-wide uppercase">
                            Reply
                        </span>
                    )}
                    <span className="text-[11px] text-muted-foreground/55 tabular-nums">
                        {new Date(thread.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })}
                    </span>
                </div>
            </div>

            {/* Body */}
            <div
                className="px-5 pb-4 prose prose-sm max-w-none leading-relaxed text-foreground/80"
                dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }}
            />

            {/* Attachments */}
            {(thread.attachments ?? []).length > 0 && (
                <div className="px-4 pb-3 pt-0.5 flex flex-wrap gap-1.5">
                    {thread.attachments!.map((a) => (
                        <a
                            key={a.id}
                            href={a.url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-[11px] text-primary hover:text-primary/80 flex items-center gap-1 bg-primary/6 hover:bg-primary/10 px-2 py-1 rounded-md border border-primary/10 transition-colors"
                        >
                            📎 {a.filename}
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Chat detail view ──────────────────────────────────────────────────────────

function ChatDetail({
    conversation,
}: {
    conversation: FullConversation;
}) {
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const conversationLink = buildConversationLink(conversation.id);

    const [localThreads, setLocalThreads] = useState<FullThread[]>(
        (conversation.threads ?? []).filter((t) => t.type === 'message' || t.type === 'note'),
    );

    // Sync local threads when Inertia reloads the selected conversation
    useEffect(() => {
        setLocalThreads(
            (conversation.threads ?? []).filter((t) => t.type === 'message' || t.type === 'note'),
        );
    }, [conversation.threads]);

    // Real-time: subscribe to livechat channel for new customer messages
    useEffect(() => {
        const ch = window.Echo?.channel(`livechat.${conversation.id}`);
        ch?.listen('.thread.created', (e: { thread: FullThread }) => {
            setLocalThreads((prev) => {
                if (prev.find((t) => t.id === e.thread.id)) return prev;
                return [...prev, e.thread];
            });
        });
        return () => window.Echo?.leave(`livechat.${conversation.id}`);
    }, [conversation.id]);

    const threads = localThreads;

    // Auto-scroll to bottom whenever threads change
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [threads.length]);

    function sendMessage() {
        if (!message.trim()) return;
        setSending(true);
        router.post(
            `/conversations/${conversation.id}/threads`,
            { body: message, type: 'message' },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selected'],
                onSuccess: () => {
                    setMessage('');
                    setSending(false);
                },
                onError: () => setSending(false),
            },
        );
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function updateStatus(status: string) {
        router.patch(
            `/conversations/${conversation.id}/status`,
            { status },
            { preserveState: true, preserveScroll: true, only: ['selected', 'conversations', 'counts'] },
        );
    }

    return (
        <div className="flex flex-col flex-1 min-h-0 overflow-hidden">
            {/* Header */}
                <div className="flex items-center gap-3 px-5 py-3 border-b border-border bg-background shrink-0">
                    <div className="flex-1 min-w-0">
                        <h2 className="text-sm font-semibold truncate">
                            {conversation.customer?.name ?? 'Unknown'}
                        </h2>
                        <div className="flex items-center gap-2 mt-0.5">
                            <Badge
                                variant={conversation.status === 'open' ? 'default' : 'secondary'}
                                className="capitalize text-xs"
                        >
                            {conversation.status}
                        </Badge>
                        <span className="text-xs text-muted-foreground">{conversation.mailbox?.name}</span>
                    </div>
                </div>
                <div className="flex items-center gap-1.5 shrink-0">
                    {conversation.status !== 'closed' && (
                        <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('closed')}>
                            Close
                        </Button>
                    )}
                    {conversation.status !== 'open' && (
                        <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('open')}>
                            Reopen
                        </Button>
                    )}
                    <a
                        href={conversationLink}
                        target="_blank"
                        rel="noreferrer"
                        title="Open conversation in new tab"
                        className="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground hover:text-foreground hover:bg-muted/20 transition-colors"
                    >
                        <ExternalLinkIcon className="h-4 w-4" />
                    </a>
                </div>
            </div>

            {/* Chat messages */}
            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-muted/10">
                {threads.length === 0 && (
                    <div className="flex items-center justify-center h-full">
                        <p className="text-sm text-muted-foreground">No messages yet</p>
                    </div>
                )}
                {threads.map((thread) => (
                    <ChatBubble key={thread.id} thread={thread} />
                ))}
                <div ref={bottomRef} />
            </div>

            {/* Input */}
            <div className="border-t border-border bg-background px-4 py-3 shrink-0">
                <div className="flex items-center gap-2">
                    <input
                        type="text"
                        className="flex-1 text-sm border border-input rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-ring bg-background"
                        placeholder="Type a message…"
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        onKeyDown={handleKeyDown}
                        disabled={sending}
                    />
                    <Button
                        type="button"
                        size="sm"
                        className="h-9 px-4 rounded-full"
                        onClick={sendMessage}
                        disabled={sending || !message.trim()}
                    >
                        <SendIcon className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

// ── Chat bubble ───────────────────────────────────────────────────────────────

function ChatBubble({ thread }: { thread: FullThread }) {
    const isFromCustomer = !!thread.customer_id;
    const author = isFromCustomer ? thread.customer : thread.user;

    return (
        <div className={cn('flex flex-col', isFromCustomer ? 'items-start' : 'items-end')}>
            <span className="text-xs text-muted-foreground mb-1 px-1">
                {author?.name ?? 'Unknown'} · {relativeTime(thread.created_at)}
            </span>
            <div
                className={cn(
                    'max-w-[70%] px-4 py-2 text-sm leading-relaxed prose prose-sm max-w-none',
                    isFromCustomer
                        ? 'bg-muted rounded-2xl rounded-tl-sm text-foreground'
                        : 'bg-primary text-primary-foreground rounded-2xl rounded-tr-sm prose-invert',
                )}
                dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }}
            />
        </div>
    );
}
