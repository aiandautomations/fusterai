import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import RichTextEditor from '@/Components/RichTextEditor';
import ConversationInspector from '@/Components/conversations/ConversationInspector';
import SlotRenderer from '@/Components/SlotRenderer';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Separator } from '@/Components/ui/separator';
import { cn, getInitials, sanitizeHtml } from '@/lib/utils';
import type { Conversation, Thread, User, Tag, Folder, Mailbox } from '@/types';
import {
    CheckCircleIcon,
    ClockIcon,
    BrainIcon,
    SendIcon,
    StickyNoteIcon,
    RefreshCwIcon,
    CheckIcon,
    XIcon,
    AlertTriangleIcon,
    MergeIcon,
    ChevronDownIcon,
    EyeIcon,
    PanelRightOpenIcon,
} from 'lucide-react';

interface SurveyData {
    rating: 'good' | 'bad';
    responded_at: string;
}

interface Props {
    conversation: Conversation & {
        aiSuggestions?: { content: string }[];
        followers?: Pick<User, 'id' | 'name' | 'avatar'>[];
    };
    agents: User[];
    tags: Tag[];
    folders: Folder[];
    convFolders: number[];
    mailboxes: Mailbox[];
    survey?: SurveyData | null;
    isFollowing: boolean;
}

const priorityConfig = {
    low:    { label: 'Low',    color: 'bg-muted text-muted-foreground' },
    normal: { label: 'Normal', color: 'bg-info/10 text-info' },
    high:   { label: 'High',   color: 'bg-warning/15 text-warning' },
    urgent: { label: 'Urgent', color: 'bg-destructive/10 text-destructive' },
} as const;

export default function ConversationShow({ conversation, agents, tags, folders, convFolders, mailboxes, survey, isFollowing }: Props) {
    const [aiSuggestion, setAiSuggestion] = useState<string | null>(
        conversation.aiSuggestions?.[0]?.content ?? null,
    );
    const [replyType, setReplyType] = useState<'message' | 'note'>('message');
    const [isRequestingAi, setIsRequestingAi] = useState(false);
    const [viewers, setViewers] = useState<{ id: number; name: string }[]>([]);
    const [threads, setThreads] = useState(conversation.threads ?? []);
    const [snoozeOpen, setSnoozeOpen] = useState(false);
    const [mobileInspectorOpen, setMobileInspectorOpen] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        body: '',
        type: 'message' as 'message' | 'note',
    });

    // Real-time: listen for new threads + AI suggestions on this conversation
    useEffect(() => {
        const ch = window.Echo?.private(`conversation.${conversation.id}`);

        ch?.listen('.thread.created', (e: { thread: Thread }) => {
            setThreads((prev) => {
                if (prev.find((t) => t.id === e.thread.id)) return prev;
                return [...prev, e.thread];
            });
        });

        ch?.listen('.ai.suggestion.ready', (e: { content: string }) => {
            setAiSuggestion(e.content);
            setIsRequestingAi(false);
        });

        // Presence: collision detection
        const presence = window.Echo?.join(`conversation.${conversation.id}.presence`);
        presence
            ?.here((members: { id: number; name: string }[]) => setViewers(members))
            ?.joining((member: { id: number; name: string }) =>
                setViewers((v) => [...v.filter((x) => x.id !== member.id), member]),
            )
            ?.leaving((member: { id: number; name: string }) =>
                setViewers((v) => v.filter((x) => x.id !== member.id)),
            );

        return () => {
            window.Echo?.leave(`conversation.${conversation.id}`);
            window.Echo?.leave(`conversation.${conversation.id}.presence`);
        };
    }, [conversation.id]);

    function submitReply(e: React.FormEvent) {
        e.preventDefault();
        if (!data.body.trim() || data.body === '<p></p>') return;
        post(`/conversations/${conversation.id}/threads`, {
            onSuccess: () => reset('body'),
        });
    }

    async function requestAiSuggestion() {
        setIsRequestingAi(true);
        setAiSuggestion(null);
        try {
            await fetch(`/ai/conversations/${conversation.id}/suggest-reply`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('[name="csrf-token"]')?.content ?? '' },
            });
        } catch {
            setIsRequestingAi(false);
        }
    }

    function acceptAiSuggestion() {
        if (!aiSuggestion) return;
        setData('body', aiSuggestion);
        setAiSuggestion(null);
    }

    function updateStatus(status: string) {
        router.patch(`/conversations/${conversation.id}/status`, { status }, { preserveScroll: true });
    }

    function assignTo(userId: string) {
        router.patch(`/conversations/${conversation.id}/assign`, { user_id: userId || null }, { preserveScroll: true });
    }

    function updatePriority(priority: string) {
        router.patch(`/conversations/${conversation.id}/priority`, { priority }, { preserveScroll: true });
    }

    function changeMailbox(mailboxId: number) {
        if (mailboxId === conversation.mailbox_id) return;
        router.patch(`/conversations/${conversation.id}/mailbox`, { mailbox_id: mailboxId }, { preserveScroll: true });
    }

    function addTag(tagId: number) {
        const current = conversation.tags?.map((tag) => tag.id) ?? [];
        if (current.includes(tagId)) return;
        router.post(`/conversations/${conversation.id}/tags`, { tag_ids: [...current, tagId] }, { preserveScroll: true });
    }

    function syncFolders(folderIds: number[]) {
        router.post(`/conversations/${conversation.id}/folders`, { folder_ids: folderIds }, { preserveScroll: true });
    }

    function toggleFollow() {
        if (isFollowing) {
            router.delete(`/conversations/${conversation.id}/follow`, { preserveScroll: true });
            return;
        }

        router.post(`/conversations/${conversation.id}/follow`, {}, { preserveScroll: true });
    }

    function snoozeUntil(minutes: number) {
        const until = new Date(Date.now() + minutes * 60 * 1000).toISOString();
        router.patch(`/conversations/${conversation.id}/snooze`, { until }, { preserveScroll: true });
        setSnoozeOpen(false);
    }

    const currentViewers = viewers.filter((v) => v.id !== conversation.assigned_user_id);

    return (
        <AppLayout>
            <Head title={conversation.subject} />

            {/* Mobile inspector backdrop */}
            {mobileInspectorOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 md:hidden"
                    onClick={() => setMobileInspectorOpen(false)}
                />
            )}

            <div className="flex h-full overflow-hidden">
                {/* ── Thread column ── */}
                <div className="flex-1 flex flex-col min-w-0 overflow-hidden">

                    {/* Header */}
                    <div className="flex items-center gap-3 px-5 py-3.5 border-b border-border/60 bg-background shrink-0">
                        <div className="flex-1 min-w-0">
                            <h1 className="text-[13.5px] font-semibold truncate leading-tight mb-1">{conversation.subject}</h1>
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <span className="text-[11px] text-muted-foreground/70">{conversation.mailbox?.name}</span>
                                <span className="text-muted-foreground/30 text-[10px]">·</span>
                                <span className={cn('text-[11px] px-1.5 py-0.5 rounded-md font-medium', priorityConfig[conversation.priority]?.color)}>
                                    {priorityConfig[conversation.priority]?.label}
                                </span>
                                <Badge variant={conversation.status === 'open' ? 'default' : 'secondary'} className="capitalize text-[11px] h-5 px-1.5">
                                    {conversation.status}
                                </Badge>
                                {/* Collision detection */}
                                {currentViewers.length > 0 && (
                                    <span className="flex items-center gap-1 text-[11px] text-warning bg-warning/10 px-1.5 py-0.5 rounded-md">
                                        <EyeIcon className="h-2.5 w-2.5" />
                                        {currentViewers.map((v) => v.name).join(', ')} viewing
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Action buttons */}
                        <div className="flex items-center gap-1 md:gap-1.5 shrink-0">
                            {/* Mobile inspector toggle */}
                            <button
                                type="button"
                                onClick={() => setMobileInspectorOpen(true)}
                                className="flex md:hidden items-center justify-center h-8 w-8 rounded-lg hover:bg-muted/70 transition-colors"
                                title="Open details"
                            >
                                <PanelRightOpenIcon className="h-4 w-4" />
                            </button>
                            <SlotRenderer name="conversation.header.actions" props={{ conversation }} />
                            {conversation.status !== 'closed' && (
                                <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('closed')}>
                                    <CheckCircleIcon className="h-3.5 w-3.5 mr-1" /> Close
                                </Button>
                            )}
                            {conversation.status !== 'open' && (
                                <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => updateStatus('open')}>
                                    Reopen
                                </Button>
                            )}
                            {conversation.status !== 'pending' && (
                                <Button size="sm" variant="ghost" className="h-8 text-xs" onClick={() => updateStatus('pending')}>
                                    <ClockIcon className="h-3.5 w-3.5 mr-1" /> Pending
                                </Button>
                            )}

                            {/* Snooze */}
                            <div className="relative">
                                <Button size="sm" variant="ghost" className="h-8 text-xs" onClick={() => setSnoozeOpen(!snoozeOpen)}>
                                    <ClockIcon className="h-3.5 w-3.5 mr-1" /> Snooze
                                    <ChevronDownIcon className="h-3 w-3 ml-0.5" />
                                </Button>
                                {snoozeOpen && (
                                    <div className="absolute right-0 top-full mt-1 bg-popover border border-border rounded-lg shadow-lg z-10 py-1 w-40">
                                        {[
                                            { label: '1 hour',    mins: 60 },
                                            { label: '3 hours',   mins: 180 },
                                            { label: 'Tomorrow',  mins: 60 * 24 },
                                            { label: '3 days',    mins: 60 * 24 * 3 },
                                            { label: '1 week',    mins: 60 * 24 * 7 },
                                        ].map(({ label, mins }) => (
                                            <button
                                                key={label}
                                                onClick={() => snoozeUntil(mins)}
                                                className="w-full text-left px-3 py-1.5 text-xs hover:bg-muted transition-colors"
                                            >
                                                {label}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Threads */}
                    <div className="flex-1 overflow-y-auto px-5 py-5 space-y-4 bg-muted/10">
                        {threads.map((thread) => (
                            <ThreadBubble key={thread.id} thread={thread} />
                        ))}
                        <SlotRenderer name="conversation.threads.after" props={{ conversation, threads }} />
                    </div>

                    {/* Reply editor */}
                    <div className="border-t border-border/60 bg-background p-4 shrink-0">
                        <SlotRenderer name="conversation.reply.before" props={{ conversation }} />
                        <div className="flex items-center gap-1 mb-3">
                            {(['message', 'note'] as const).map((t) => (
                                <button
                                    key={t}
                                    onClick={() => { setReplyType(t); setData('type', t); }}
                                    className={cn(
                                        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-all',
                                        replyType === t
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
                        </div>

                        <form onSubmit={submitReply}>
                            <div className={cn(replyType === 'note' && '[&_.ProseMirror]:bg-warning/10')}>
                                <RichTextEditor
                                    value={data.body}
                                    onChange={(html) => setData('body', html)}
                                    placeholder={replyType === 'message' ? 'Write your reply…' : 'Write an internal note…'}
                                    minHeight="100px"
                                    mailboxId={conversation.mailbox_id}
                                />
                            </div>

                            <div className="flex items-center justify-between mt-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-info hover:bg-info/10 h-8"
                                    onClick={requestAiSuggestion}
                                    disabled={isRequestingAi}
                                >
                                    <BrainIcon className={cn('h-3.5 w-3.5 mr-1.5', isRequestingAi && 'animate-pulse')} />
                                    {isRequestingAi ? 'Generating…' : 'AI Suggest'}
                                </Button>
                                <Button type="submit" size="sm" className="h-8" disabled={processing}>
                                    <SendIcon className="h-3.5 w-3.5 mr-1.5" />
                                    {replyType === 'message' ? 'Send Reply' : 'Add Note'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* ── Right sidebar ── */}
                <div className={cn(
                    'shrink-0 border-l border-border flex flex-col overflow-y-auto bg-background',
                    // Desktop: always visible fixed width
                    'hidden md:flex md:w-72',
                    // Mobile: slide-in overlay from the right
                    mobileInspectorOpen && 'flex fixed inset-y-0 right-0 z-50 w-80 md:relative md:inset-auto md:z-auto shadow-2xl',
                )}>

                    {/* Mobile close button */}
                    {mobileInspectorOpen && (
                        <div className="flex md:hidden items-center justify-between px-4 py-3 border-b border-border shrink-0">
                            <span className="text-sm font-semibold">Details</span>
                            <button
                                type="button"
                                onClick={() => setMobileInspectorOpen(false)}
                                className="rounded-lg p-1.5 hover:bg-muted/70 transition-colors"
                            >
                                <XIcon className="h-4 w-4" />
                            </button>
                        </div>
                    )}

                    {/* AI Suggestion */}
                    {(aiSuggestion || isRequestingAi) && (
                        <div className="p-4 border-b border-border bg-info/10">
                            <div className="flex items-center gap-1.5 mb-2">
                                <BrainIcon className="h-4 w-4 text-info" />
                                <span className="text-xs font-semibold text-info">AI Suggestion</span>
                            </div>
                            {isRequestingAi ? (
                                <div className="space-y-2 py-1">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className={`h-3 bg-info/25 rounded animate-pulse ${i === 3 ? 'w-3/5' : ''}`} />
                                    ))}
                                </div>
                            ) : (
                                <>
                                    <div
                                        className="text-xs text-foreground leading-relaxed bg-background rounded-md p-2.5 mb-2 border border-info/20 max-h-40 overflow-y-auto"
                                        dangerouslySetInnerHTML={{ __html: sanitizeHtml(aiSuggestion ?? '') }}
                                    />
                                    <div className="flex gap-2">
                                        <Button size="sm" className="flex-1 h-7 text-xs" onClick={acceptAiSuggestion}>
                                            <CheckIcon className="h-3 w-3 mr-1" /> Accept
                                        </Button>
                                        <Button size="sm" variant="outline" className="h-7 text-xs" onClick={requestAiSuggestion}>
                                            <RefreshCwIcon className="h-3 w-3 mr-1" /> Retry
                                        </Button>
                                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => setAiSuggestion(null)}>
                                            <XIcon className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {/* AI Summary */}
                    {conversation.ai_summary && (
                        <div className="p-4 border-b border-border">
                            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1.5">AI Summary</p>
                            <p className="text-xs text-foreground leading-relaxed whitespace-pre-wrap">{conversation.ai_summary}</p>
                        </div>
                    )}

                    <ConversationInspector
                        conversation={conversation}
                        agents={agents}
                        tags={tags}
                        folders={folders}
                        mailboxes={mailboxes}
                        selectedFolderIds={convFolders}
                        isFollowing={isFollowing}
                        survey={survey}
                        onStatusChange={updateStatus}
                        onPriorityChange={updatePriority}
                        onAssignChange={assignTo}
                        onMailboxChange={changeMailbox}
                        onAddTag={addTag}
                        onAddFolder={(folderId) => syncFolders([...convFolders, folderId])}
                        onRemoveFolder={(folderId) => syncFolders(convFolders.filter((id) => id !== folderId))}
                        onToggleFollow={toggleFollow}
                    />

                    <SlotRenderer name="conversation.sidebar.bottom" props={{ conversation, survey, conversationStatus: conversation.status }} />
                </div>
            </div>
        </AppLayout>
    );
}

// ── Thread bubble ────────────────────────────────────────────────

function ThreadBubble({ thread }: { thread: Thread }) {
    const isFromCustomer = !!thread.customer_id;
    const isNote = thread.type === 'note';
    const isActivity = thread.type === 'activity';
    const author = isFromCustomer ? thread.customer : thread.user;

    if (isActivity) {
        return (
            <div className="flex items-center gap-3 py-1">
                <div className="flex-1 h-px bg-border" />
                <span className="text-xs text-muted-foreground shrink-0 px-2" dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }} />
                <div className="flex-1 h-px bg-border" />
            </div>
        );
    }

    return (
        <div className={cn('flex gap-3', !isFromCustomer && 'flex-row-reverse')}>
            <Avatar className="size-6 mt-1 shrink-0">
                <AvatarImage src={author?.avatar ?? undefined} alt={author?.name ?? 'Unknown'} />
                <AvatarFallback>{getInitials(author?.name)}</AvatarFallback>
            </Avatar>
            <div className={cn('max-w-[75%]', !isFromCustomer && 'items-end flex flex-col')}>
                <div className={cn('flex items-center gap-2 mb-1', !isFromCustomer && 'flex-row-reverse')}>
                    <span className="text-xs font-semibold">{author?.name ?? 'Unknown'}</span>
                    <span className="text-xs text-muted-foreground">
                        {new Date(thread.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })}
                    </span>
                    {isNote && <span className="text-xs bg-warning/20 text-warning px-1.5 py-0.5 rounded-full font-medium">Note</span>}
                </div>
                <div
                    className={cn(
                        'rounded-xl px-3.5 py-2.5 text-sm leading-relaxed prose prose-sm max-w-none',
                        isNote
                            ? 'bg-warning/10 border border-warning/30 text-foreground shadow-sm'
                            : isFromCustomer
                                ? 'bg-card border border-border/60 text-foreground shadow-sm'
                                : 'bg-primary text-primary-foreground prose-invert shadow-sm',
                    )}
                    dangerouslySetInnerHTML={{ __html: sanitizeHtml(thread.body) }}
                />
                {(thread.attachments ?? []).length > 0 && (
                    <div className="mt-1.5 flex flex-wrap gap-1">
                        {thread.attachments!.map((a) => (
                            <a
                                key={a.id}
                                href={a.url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-primary hover:underline flex items-center gap-1 bg-primary/5 px-2 py-1 rounded"
                            >
                                📎 {a.filename}
                            </a>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
