import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { cn, getInitials, sanitizeHtml } from '@/lib/utils';
import type { Conversation, Thread, PageProps } from '@/types';
import { MessageSquareIcon, SendIcon } from 'lucide-react';
import { usePage } from '@inertiajs/react';

interface Props {
    conversations: Conversation[];
}

function relativeTime(dateStr?: string): string {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

export default function LiveChatIndex({ conversations: initialConversations }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [conversations, setConversations] = useState<Conversation[]>(initialConversations);
    const [selectedId, setSelectedId] = useState<number | null>(initialConversations[0]?.id ?? null);
    const [threads, setThreads] = useState<Thread[]>([]);
    const [loadingThreads, setLoadingThreads] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, reset } = useForm({ body: '', type: 'message' });

    const selected = conversations.find((c) => c.id === selectedId) ?? null;

    // ── Fetch threads for selected conversation ───────────────────────────────

    useEffect(() => {
        if (!selectedId) {
            setThreads([]);
            return;
        }
        setLoadingThreads(true);

        window.axios
            .get(`/conversations/${selectedId}`, { headers: { 'X-Inertia': 'true', Accept: 'application/json' } })
            .then((res) => {
                const conv: Conversation =
                    (res.data as { props?: { conversation?: Conversation }; conversation?: Conversation }).props?.conversation ??
                    (res.data as { conversation?: Conversation }).conversation ??
                    (res.data as Conversation);
                setThreads(conv.threads ?? []);
            })
            .catch(() => setThreads([]))
            .finally(() => setLoadingThreads(false));
    }, [selectedId]);

    // Scroll to bottom on new threads
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [threads]);

    // ── Real-time: workspace-level updates to refresh conversation list ────────

    useEffect(() => {
        const ch = window.Echo?.private(`workspace.${auth.user?.workspace_id}`);
        ch?.listen('.conversation.updated', () => {
            router.reload({ only: ['conversations'] });
        });
        return () => ch?.stopListening('.conversation.updated');
    }, [auth.user?.workspace_id]);

    // ── Real-time: per-conversation livechat channel for new threads ──────────

    useEffect(() => {
        if (!selectedId) return;

        const channelName = `livechat.${selectedId}`;
        const ch = window.Echo?.channel(channelName);

        ch?.listen('.thread.created', (event: { thread: Thread }) => {
            setThreads((prev) => {
                if (prev.find((t) => t.id === event.thread.id)) return prev;
                return [...prev, event.thread];
            });
        });

        return () => {
            window.Echo?.leave(channelName);
        };
    }, [selectedId]);

    // ── Reply submission ──────────────────────────────────────────────────────

    function submitReply(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedId || !data.body.trim()) return;

        post(`/conversations/${selectedId}/threads`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    // ── Render ────────────────────────────────────────────────────────────────

    return (
        <AppLayout>
            <Head title="Live Chat" />

            <div className="flex h-[calc(100vh-64px)] overflow-hidden">
                {/* ── Conversation list ─────────────────────────────────── */}
                <aside className="w-80 flex-shrink-0 border-r border-border flex flex-col bg-background">
                    <div className="px-4 py-3 border-b border-border flex items-center gap-2">
                        <MessageSquareIcon className="w-5 h-5 text-primary" />
                        <h1 className="font-semibold text-foreground">Live Chat</h1>
                        <Badge variant="secondary" className="ml-auto">
                            {conversations.length}
                        </Badge>
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        {conversations.length === 0 && (
                            <div className="py-12 text-center text-muted-foreground text-sm">No active live chat conversations.</div>
                        )}

                        {conversations.map((conv) => (
                            <button
                                key={conv.id}
                                onClick={() => setSelectedId(conv.id)}
                                className={cn(
                                    'w-full text-left px-4 py-3 flex items-start gap-3 transition-colors',
                                    selectedId === conv.id ? 'bg-accent border-l-2 border-primary' : 'hover:bg-muted/50',
                                )}
                            >
                                <Avatar className="w-8 h-8 flex-shrink-0">
                                    <AvatarImage src={conv.customer?.avatar ?? undefined} alt={conv.customer?.name ?? 'Visitor'} />
                                    <AvatarFallback className="bg-primary/10 text-primary text-xs font-bold">
                                        {getInitials(conv.customer?.name ?? 'Visitor')}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between gap-1">
                                        <span className="font-medium text-sm text-foreground truncate">
                                            {conv.customer?.name ?? 'Visitor'}
                                        </span>
                                        <span className="text-xs text-muted-foreground flex-shrink-0">
                                            {relativeTime(conv.last_reply_at)}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground truncate mt-0.5">{conv.subject}</p>
                                </div>
                            </button>
                        ))}
                    </div>
                </aside>

                {/* ── Thread view ───────────────────────────────────────── */}
                <main className="flex-1 flex flex-col bg-muted/30 min-w-0">
                    {!selected ? (
                        <div className="flex-1 flex items-center justify-center text-muted-foreground">
                            <div className="text-center">
                                <MessageSquareIcon className="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p>Select a conversation to view messages</p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Header */}
                            <div className="bg-background border-b border-border px-6 py-3 flex items-center gap-3 flex-shrink-0">
                                <Avatar className="w-8 h-8">
                                    <AvatarImage src={selected.customer?.avatar ?? undefined} alt={selected.customer?.name ?? 'Visitor'} />
                                    <AvatarFallback className="bg-primary/10 text-primary text-xs font-bold">
                                        {getInitials(selected.customer?.name ?? 'Visitor')}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-semibold text-foreground text-sm">{selected.customer?.name ?? 'Visitor'}</div>
                                    {selected.customer?.email && (
                                        <div className="text-xs text-muted-foreground">{selected.customer.email}</div>
                                    )}
                                </div>
                                <Badge variant="outline" className="ml-auto text-xs">
                                    Live Chat
                                </Badge>
                            </div>

                            {/* Messages */}
                            <div className="flex-1 overflow-y-auto px-6 py-4 flex flex-col gap-3">
                                {loadingThreads ? (
                                    <div className="text-center text-muted-foreground text-sm py-8">Loading messages…</div>
                                ) : threads.length === 0 ? (
                                    <div className="text-center text-muted-foreground text-sm py-8">No messages yet.</div>
                                ) : (
                                    threads.map((thread) => {
                                        const isVisitor = !!(thread.customer_id ?? thread.customer);
                                        return (
                                            <div
                                                key={thread.id}
                                                className={cn('flex flex-col max-w-[70%]', isVisitor ? 'self-start' : 'self-end items-end')}
                                            >
                                                <div
                                                    className={cn(
                                                        'px-4 py-2.5 rounded-xl text-sm leading-relaxed',
                                                        isVisitor
                                                            ? 'bg-background border border-border text-foreground rounded-tl-sm'
                                                            : 'bg-primary text-primary-foreground rounded-tr-sm',
                                                    )}
                                                    dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }}
                                                />
                                                <span className="text-[11px] text-muted-foreground mt-1 px-1">
                                                    {isVisitor ? (thread.customer?.name ?? 'Visitor') : (thread.user?.name ?? 'You')} ·{' '}
                                                    {relativeTime(thread.created_at)}
                                                </span>
                                            </div>
                                        );
                                    })
                                )}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Reply box */}
                            <form
                                onSubmit={submitReply}
                                className="bg-background border-t border-border px-4 py-3 flex items-end gap-3 flex-shrink-0"
                            >
                                <textarea
                                    value={data.body}
                                    onChange={(e) => setData('body', e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            submitReply(e as unknown as React.FormEvent);
                                        }
                                    }}
                                    placeholder="Type a reply… (Enter to send, Shift+Enter for newline)"
                                    rows={2}
                                    className="flex-1 resize-none border border-input rounded-lg px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/30 font-sans bg-background"
                                />
                                <Button
                                    type="submit"
                                    disabled={processing || !data.body.trim()}
                                    size="sm"
                                    className="flex items-center gap-1.5"
                                >
                                    <SendIcon className="w-4 h-4" />
                                    Send
                                </Button>
                            </form>
                        </>
                    )}
                </main>
            </div>
        </AppLayout>
    );
}
