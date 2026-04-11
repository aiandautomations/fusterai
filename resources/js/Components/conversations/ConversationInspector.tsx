import { Link, usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn, getInitials } from '@/lib/utils';
import type { Conversation, Folder, Mailbox, Tag, User } from '@/types';
import StatusDot from '@/Components/StatusDot';
import { EyeIcon, EyeOffIcon, XIcon, ChevronDownIcon, ArrowUpRightIcon, UserCheckIcon } from 'lucide-react';
import type { PageProps } from '@/types';

interface SurveyData {
    rating: 'good' | 'bad';
    responded_at: string;
}

type InspectorConversation = Conversation & {
    followers?: Pick<User, 'id' | 'name' | 'avatar'>[];
};

interface Props {
    conversation: InspectorConversation;
    agents: { id: number; name: string; avatar?: string; status?: string }[];
    tags: Tag[];
    folders?: Folder[];
    mailboxes?: Pick<Mailbox, 'id' | 'name'>[];
    selectedFolderIds?: number[];
    isFollowing?: boolean;
    survey?: SurveyData | null;
    onStatusChange: (status: string) => void;
    onPriorityChange: (priority: string) => void;
    onAssignChange: (userId: string) => void;
    onMailboxChange?: (mailboxId: number) => void;
    onAddTag: (tagId: number) => void;
    onAddFolder?: (folderId: number) => void;
    onRemoveFolder?: (folderId: number) => void;
    onToggleFollow?: () => void;
    className?: string;
}

const STATUS_CONFIG = {
    open:    { label: 'Open',    dot: 'bg-emerald-500', active: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400' },
    pending: { label: 'Pending', dot: 'bg-amber-400',   active: 'bg-amber-400/12 text-amber-700 dark:text-amber-400' },
    closed:  { label: 'Closed',  dot: 'bg-muted-foreground/40', active: 'bg-muted/60 text-foreground/70' },
    spam:    { label: 'Spam',    dot: 'bg-rose-400',    active: 'bg-rose-400/12 text-rose-700 dark:text-rose-400' },
} as const;

const PRIORITY_CONFIG = {
    low:    { label: 'Low',    active: 'bg-background text-foreground/70 shadow-sm' },
    normal: { label: 'Normal', active: 'bg-background text-info shadow-sm' },
    high:   { label: 'High',   active: 'bg-background text-warning shadow-sm' },
    urgent: { label: 'Urgent', active: 'bg-background text-destructive shadow-sm' },
} as const;

export default function ConversationInspector({
    conversation,
    agents,
    tags,
    folders = [],
    mailboxes = [],
    selectedFolderIds = [],
    isFollowing = false,
    survey,
    onStatusChange,
    onPriorityChange,
    onAssignChange,
    onMailboxChange,
    onAddTag,
    onAddFolder,
    onRemoveFolder,
    onToggleFollow,
    className,
}: Props) {
    const availableTags = tags.filter((tag) => !(conversation.tags ?? []).find((current) => current.id === tag.id));
    const activeFolders = folders.filter((folder) => selectedFolderIds.includes(folder.id));
    const availableFolders = folders.filter((folder) => !selectedFolderIds.includes(folder.id));
    const followers = conversation.followers ?? [];
    const assignedAgent = agents.find((a) => a.id === conversation.assigned_user_id);
    const currentUser = usePage<PageProps>().props.auth.user;
    const isAssignedToMe = conversation.assigned_user_id === currentUser.id;

    return (
        <div className={cn('flex flex-col overflow-y-auto bg-background', className)}>

            {/* ── Customer ── */}
            <div className="px-4 py-4 border-b border-border">
                <p className="mb-2.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">Customer</p>
                {conversation.customer && (
                    <Link
                        href={`/customers/${conversation.customer.id}`}
                        className="flex items-center gap-3 group rounded-xl p-2 -mx-2 hover:bg-muted/40 transition-colors"
                    >
                        <Avatar className="size-9 shrink-0">
                            <AvatarImage src={conversation.customer.avatar ?? undefined} alt={conversation.customer.name} />
                            <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                {getInitials(conversation.customer.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-1">
                                <p className="truncate text-[13px] font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {conversation.customer.name}
                                </p>
                                <ArrowUpRightIcon className="h-3 w-3 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                            </div>
                            <p className="truncate text-[11px] text-muted-foreground">{conversation.customer.email}</p>
                            {conversation.customer.company && (
                                <p className="truncate text-[11px] text-muted-foreground/70">{conversation.customer.company}</p>
                            )}
                        </div>
                    </Link>
                )}
            </div>

            {/* ── Conversation details ── */}
            <div className="px-4 py-4 border-b border-border space-y-4">
                <p className="text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">Conversation</p>

                {/* Mailbox */}
                {mailboxes.length > 0 && onMailboxChange && (
                    <div className="space-y-1.5">
                        <p className="text-[11px] text-muted-foreground/70 font-medium">Mailbox</p>
                        <DropdownMenu>
                            <DropdownMenuTrigger className="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[12px] font-medium hover:bg-muted/50 transition-colors text-left border border-transparent hover:border-border">
                                {mailboxes.find(m => m.id === conversation.mailbox_id)?.name ?? 'Select mailbox'}
                                <ChevronDownIcon className="h-3 w-3 text-muted-foreground/50 ml-1 shrink-0" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-52">
                                {mailboxes.map((mailbox) => (
                                    <DropdownMenuItem
                                        key={mailbox.id}
                                        onSelect={() => onMailboxChange(mailbox.id)}
                                        className={cn('text-xs', mailbox.id === conversation.mailbox_id && 'text-primary font-medium')}
                                    >
                                        {mailbox.name}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}

                {/* Status — inline pill row */}
                <div className="space-y-1.5">
                    <p className="text-[11px] text-muted-foreground/70 font-medium">Status</p>
                    <div className="flex flex-wrap gap-1">
                        {(Object.keys(STATUS_CONFIG) as (keyof typeof STATUS_CONFIG)[]).map((s) => {
                            const cfg = STATUS_CONFIG[s];
                            const isActive = conversation.status === s;
                            return (
                                <button
                                    key={s}
                                    type="button"
                                    onClick={() => onStatusChange(s)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium transition-all',
                                        isActive
                                            ? cfg.active
                                            : 'text-muted-foreground/60 hover:bg-muted/50 hover:text-foreground/80',
                                    )}
                                >
                                    <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', isActive ? cfg.dot : 'bg-muted-foreground/25')} />
                                    {cfg.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Priority — segmented control */}
                <div className="space-y-1.5">
                    <p className="text-[11px] text-muted-foreground/70 font-medium">Priority</p>
                    <div className="grid grid-cols-4 gap-0.5 p-0.5 rounded-lg bg-muted/40">
                        {(Object.keys(PRIORITY_CONFIG) as (keyof typeof PRIORITY_CONFIG)[]).map((p) => {
                            const cfg = PRIORITY_CONFIG[p];
                            const isActive = conversation.priority === p;
                            return (
                                <button
                                    key={p}
                                    type="button"
                                    onClick={() => onPriorityChange(p)}
                                    className={cn(
                                        'py-1 rounded-md text-[10.5px] font-semibold transition-all text-center',
                                        isActive ? cfg.active : 'text-muted-foreground/55 hover:text-foreground/70',
                                    )}
                                >
                                    {cfg.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Assigned to — avatar + name button */}
                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <p className="text-[11px] text-muted-foreground/70 font-medium">Assigned to</p>
                        {!isAssignedToMe && (
                            <button
                                type="button"
                                onClick={() => onAssignChange(String(currentUser.id))}
                                className="flex items-center gap-1 text-[10px] text-primary hover:text-primary/80 font-medium transition-colors"
                                title="Assign to me"
                            >
                                <UserCheckIcon className="h-3 w-3" />
                                Assign to me
                            </button>
                        )}
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-muted/50 transition-colors group border border-transparent hover:border-border">
                            {assignedAgent ? (
                                <>
                                    <div className="relative shrink-0">
                                        <Avatar className="size-5">
                                            <AvatarFallback className="bg-primary/10 text-primary text-[9px] font-bold">
                                                {getInitials(assignedAgent.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <StatusDot status={assignedAgent.status} size="sm" />
                                    </div>
                                    <span className="text-[12px] font-medium text-foreground/90 flex-1 text-left truncate">{assignedAgent.name}</span>
                                </>
                            ) : (
                                <>
                                    <div className="size-5 rounded-full border-2 border-dashed border-muted-foreground/25 shrink-0" />
                                    <span className="text-[12px] text-muted-foreground flex-1 text-left">Unassigned</span>
                                </>
                            )}
                            <ChevronDownIcon className="h-3 w-3 text-muted-foreground/40 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-52">
                            <DropdownMenuItem
                                onSelect={() => onAssignChange('')}
                                className={cn('text-xs', !conversation.assigned_user_id && 'text-muted-foreground font-medium')}
                            >
                                <div className="size-5 rounded-full border-2 border-dashed border-muted-foreground/25 mr-1.5" />
                                Unassigned
                            </DropdownMenuItem>
                            {agents.map((agent) => (
                                <DropdownMenuItem
                                    key={agent.id}
                                    onSelect={() => onAssignChange(String(agent.id))}
                                    className={cn('text-xs', agent.id === conversation.assigned_user_id && 'text-primary font-medium')}
                                >
                                    <div className="relative mr-1.5 shrink-0">
                                        <Avatar className="size-5">
                                            <AvatarFallback className="bg-primary/10 text-primary text-[9px] font-bold">
                                                {getInitials(agent.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <StatusDot status={agent.status} size="sm" />
                                    </div>
                                    {agent.name}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            {/* ── Tags ── */}
            <div className="px-4 py-4 border-b border-border">
                <p className="mb-2.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">Tags</p>
                <div className="flex flex-wrap gap-1 mb-2">
                    {(conversation.tags ?? []).map((tag) => (
                        <span
                            key={tag.id}
                            className="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                            style={{ backgroundColor: `${tag.color}20`, color: tag.color }}
                        >
                            {tag.name}
                        </span>
                    ))}
                </div>
                {availableTags.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger className="flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground transition-colors group">
                            <span className="size-3.5 rounded-full border border-dashed border-muted-foreground/40 inline-flex items-center justify-center text-[10px] group-hover:border-foreground/40">+</span>
                            Add tag
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-44">
                            {availableTags.map((tag) => (
                                <DropdownMenuItem key={tag.id} onSelect={() => onAddTag(tag.id)} className="text-xs gap-2">
                                    <span className="size-2 rounded-full shrink-0" style={{ backgroundColor: tag.color }} />
                                    {tag.name}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            {/* ── Folders ── */}
            {folders.length > 0 && (
                <div className="px-4 py-4 border-b border-border">
                    <p className="mb-2.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">Folders</p>
                    <div className="flex flex-wrap gap-1 mb-2">
                        {activeFolders.map((folder) => (
                            <span
                                key={folder.id}
                                className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium text-white"
                                style={{ backgroundColor: folder.color }}
                            >
                                {folder.name}
                                {onRemoveFolder && (
                                    <button
                                        type="button"
                                        className="ml-0.5 opacity-75 transition-opacity hover:opacity-100"
                                        onClick={() => onRemoveFolder(folder.id)}
                                    >
                                        <XIcon className="h-2.5 w-2.5" />
                                    </button>
                                )}
                            </span>
                        ))}
                    </div>
                    {availableFolders.length > 0 && onAddFolder && (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground transition-colors group">
                                <span className="size-3.5 rounded-full border border-dashed border-muted-foreground/40 inline-flex items-center justify-center text-[10px] group-hover:border-foreground/40">+</span>
                                Add to folder
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-44">
                                {availableFolders.map((folder) => (
                                    <DropdownMenuItem key={folder.id} onSelect={() => onAddFolder(folder.id)} className="text-xs gap-2">
                                        <span className="size-2 rounded-full shrink-0" style={{ backgroundColor: folder.color }} />
                                        {folder.name}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            )}

            {/* ── Followers ── */}
            {(onToggleFollow || followers.length > 0) && (
                <div className="px-4 py-4 border-b border-border">
                    <div className="flex items-center justify-between mb-2.5">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">Followers</p>
                        {onToggleFollow && (
                            <button
                                type="button"
                                onClick={onToggleFollow}
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] transition-colors font-medium',
                                    isFollowing
                                        ? 'bg-primary/8 text-primary hover:bg-primary/12'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50',
                                )}
                            >
                                {isFollowing ? <EyeOffIcon className="h-3 w-3" /> : <EyeIcon className="h-3 w-3" />}
                                {isFollowing ? 'Unfollow' : 'Follow'}
                            </button>
                        )}
                    </div>
                    {followers.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                            {followers.map((follower) => (
                                <Avatar key={follower.id} className="h-6 w-6" title={follower.name}>
                                    <AvatarImage src={follower.avatar ?? undefined} />
                                    <AvatarFallback className="text-[10px]">{getInitials(follower.name)}</AvatarFallback>
                                </Avatar>
                            ))}
                        </div>
                    ) : (
                        <p className="text-[11px] italic text-muted-foreground/50">No followers yet</p>
                    )}
                </div>
            )}

            {/* ── CSAT ── */}
            {(survey || conversation.status === 'closed') && (
                <div className="px-4 py-4">
                    <p className="mb-2.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground/60">CSAT</p>
                    {survey ? (
                        <div className="flex items-center gap-2.5">
                            <span className="text-lg">{survey.rating === 'good' ? '👍' : '👎'}</span>
                            <div>
                                <p className={cn('text-[12px] font-semibold', survey.rating === 'good' ? 'text-emerald-600' : 'text-destructive')}>
                                    {survey.rating === 'good' ? 'Positive' : 'Negative'}
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {new Date(survey.responded_at).toLocaleDateString()}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <p className="text-[11px] italic text-muted-foreground/50">No response yet</p>
                    )}
                </div>
            )}
        </div>
    );
}
