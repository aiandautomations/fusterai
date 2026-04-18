import React from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import SlotRenderer from '@/Components/SlotRenderer';
import NotificationBell from '@/Components/NotificationBell';
import StatusDot from '@/Components/StatusDot';
import { type PageProps, type Folder } from '@/types';
import { type AgentStatus, AGENT_STATUS_COLORS, AGENT_STATUS_LABELS } from '@/lib/agentStatus';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import GlobalSearch from '@/Components/GlobalSearch';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { cn, getInitials } from '@/lib/utils';
import { applyAppearance, persistAppearance, readStoredAppearance } from '@/lib/appearance';
import { toast } from 'sonner';
import {
    InboxIcon,
    MailboxIcon,
    UsersIcon,
    SettingsIcon,
    BookOpenIcon,
    BarChartIcon,
    PanelLeftIcon,
    ZapIcon,
    LogOutIcon,
    MessageSquareIcon,
    BotIcon,
    SunIcon,
    MoonIcon,
    MonitorIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    PlusIcon,
    FolderIcon,
    TagIcon,
    MessageSquareTextIcon,
    BrainIcon,
    PuzzleIcon,
    UserIcon,
    MenuIcon,
    XIcon,
    ClockIcon,
    MailIcon,
    KeyIcon,
    ClipboardListIcon,
    LayoutDashboardIcon,
} from 'lucide-react';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
    /** Set true for full-height fixed panels (conversations view). Disables main scroll. */
    fullHeight?: boolean;
    /** Callback to open the ViewBuilderModal in the parent page. */
    onCreateView?: () => void;
}

interface SidebarMailbox {
    id: number;
    name: string;
}
interface SidebarTag {
    id: number;
    name: string;
    color: string;
}

export default function AppLayout({ children, fullHeight, onCreateView }: AppLayoutProps) {
    const {
        auth,
        flash,
        counts,
        mailboxes,
        tags,
        folders,
        customViews,
        appearance: appearanceDefaults,
        branding,
        agentStatuses: initialAgentStatuses,
    } = usePage<
        PageProps & {
            counts?: Record<string, number>;
            mailboxes?: SidebarMailbox[];
            tags?: SidebarTag[];
            folders?: Folder[];
            customViews?: import('@/types').CustomView[];
            [key: string]: unknown;
        }
    >().props;
    const [agentStatuses, setAgentStatuses] = React.useState<Record<number, string>>(initialAgentStatuses ?? {});
    const [collapsed, setCollapsed] = React.useState(false);
    const [mobileOpen, setMobileOpen] = React.useState(false);
    const [inboxOpen, setInboxOpen] = React.useState(true);
    const [tagsOpen, setTagsOpen] = React.useState(false);
    const [foldersOpen, setFoldersOpen] = React.useState(true);
    const [viewsOpen, setViewsOpen] = React.useState(true);
    const toastShown = React.useRef<string | null>(null);
    const [appearance, setAppearance] = React.useState(() => readStoredAppearance(appearanceDefaults));
    const path = window.location.pathname;
    const currentUserStatus = (agentStatuses[auth.user?.id] ?? auth.user?.status ?? 'offline') as AgentStatus;
    const fullPath = path + window.location.search;
    const searchParams = new URLSearchParams(window.location.search);
    const activeMailbox = searchParams.get('mailbox') ?? 'all';

    React.useEffect(() => {
        applyAppearance(appearance);
        persistAppearance(appearance);

        if (appearance.mode !== 'system') return;

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => applyAppearance(appearance);
        media.addEventListener('change', listener);
        return () => media.removeEventListener('change', listener);
    }, [appearance]);

    React.useEffect(() => {
        const message = flash?.success ?? flash?.error ?? null;
        if (!message || toastShown.current === message) return;

        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        toastShown.current = message;
    }, [flash?.success, flash?.error]);

    // Real-time agent status updates via Reverb
    React.useEffect(() => {
        if (!auth.user?.workspace_id) return;
        const channel = window.Echo.private(`workspace.${auth.user.workspace_id}`);
        channel.listen('.agent.status.changed', (e: { user_id: number; status: string }) => {
            setAgentStatuses((prev) => ({ ...prev, [e.user_id]: e.status }));
        });
        return () => {
            channel.stopListening('.agent.status.changed');
        };
    }, [auth.user?.workspace_id]);

    function cycleTheme() {
        setAppearance((current) => ({
            ...current,
            mode: current.mode === 'light' ? 'dark' : current.mode === 'dark' ? 'system' : 'light',
        }));
    }

    function navClass(href: string) {
        return cn(
            'group relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-all duration-150 cursor-pointer',
            path.startsWith(href)
                ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
            sidebarCollapsed && 'justify-center px-2',
        );
    }

    function sectionHeaderClass() {
        return 'flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[11px] font-semibold text-sidebar-foreground/55 uppercase tracking-[0.12em] transition-colors hover:bg-sidebar-accent/45 hover:text-sidebar-foreground/80';
    }

    function switchMailbox(value: string) {
        const status = searchParams.get('status') ?? 'open';
        const assigned = searchParams.get('assigned') ?? (auth.user?.role === 'agent' ? 'me' : null);

        router.get(
            '/conversations',
            {
                status,
                mailbox: value === 'all' ? undefined : value,
                assigned: assigned ?? undefined,
                page: undefined,
                conversation: undefined,
                tag: undefined,
            },
            { preserveScroll: true, replace: true },
        );
    }

    const inboxFilters = [
        { label: 'All Open', href: '/conversations?status=open', count: counts?.open },
        { label: 'Mine', href: '/conversations?assigned=me&status=open', count: counts?.mine },
        { label: 'Unassigned', href: '/conversations?assigned=none&status=open' },
        { label: 'Pending', href: '/conversations?status=pending', count: counts?.pending },
        { label: 'Snoozed', href: '/conversations?status=snoozed', count: counts?.snoozed },
        { label: 'Closed', href: '/conversations?status=closed' },
    ];

    const isCommunicationWorkspace = path.startsWith('/conversations') || path.startsWith('/live-chat');
    const sidebarCollapsed = collapsed;
    const inboxNavActive = ['/mailboxes', '/tags', '/settings/folders', '/settings/canned-responses'].some((h) => path.startsWith(h));
    const customersNavActive = path.startsWith('/customers');
    const analyticsNavActive = path.startsWith('/reports');
    const aiNavActive = ['/automation', '/ai/', '/settings/ai'].some((h) => path.startsWith(h));
    const customNavActive = ['/settings/appearance', '/settings/live-chat'].some((h) => path.startsWith(h));
    const settingsNavActive =
        [
            '/settings/general',
            '/settings/users',
            '/settings/modules',
            '/settings/email',
            '/settings/api-keys',
            '/settings/audit-log',
            '/settings',
        ].some((h) => path.startsWith(h)) &&
        !customNavActive &&
        !aiNavActive;

    React.useEffect(() => {
        if (!isCommunicationWorkspace) return;
        setInboxOpen(true);
    }, [isCommunicationWorkspace, path]);

    // Close mobile sidebar on navigation
    React.useEffect(() => {
        setMobileOpen(false);
    }, [path]);

    return (
        <div className="flex h-full bg-muted/40 md:p-3 gap-0">
            {/* Mobile sidebar backdrop */}
            {mobileOpen && <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={() => setMobileOpen(false)} />}

            <div
                className={cn(
                    'flex min-w-0 flex-1 rounded-none md:rounded-xl shadow-[0_2px_24px_rgba(0,0,0,0.08)] dark:shadow-[0_2px_24px_rgba(0,0,0,0.28)] overflow-hidden',
                )}
            >
                <aside
                    className={cn(
                        'relative flex flex-col bg-sidebar text-sidebar-foreground transition-all duration-200 shrink-0 backdrop-blur-md',
                        // Desktop: collapsible width
                        'hidden md:flex',
                        sidebarCollapsed ? 'md:w-16' : 'md:w-64',
                        // Mobile: fixed overlay
                        mobileOpen && 'flex fixed inset-y-0 left-0 z-50 w-72 md:relative md:inset-auto md:z-auto',
                    )}
                >
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_10%_0%,hsl(var(--primary)/0.2),transparent_40%)]" />
                    <div className="pointer-events-none absolute inset-y-0 right-0 w-px bg-white/20 dark:bg-black/20" />
                    {/* Logo + toggle */}
                    <div className="relative z-10 flex h-16 items-center justify-between px-3.5 border-b border-border/60 shrink-0">
                        {(!sidebarCollapsed || mobileOpen) && (
                            <Link href="/dashboard" className="flex items-center gap-2.5 min-w-0">
                                {branding?.logo_url ? (
                                    <img
                                        src={branding.logo_url}
                                        alt={branding.name ?? 'Logo'}
                                        className="h-8 w-8 rounded-xl object-contain"
                                    />
                                ) : (
                                    <div className="inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-sidebar-primary/15 ring-1 ring-sidebar-primary/25">
                                        <ZapIcon className="h-4 w-4 text-sidebar-primary" />
                                    </div>
                                )}
                                <span className="font-bold text-[15px] leading-none tracking-tight truncate">
                                    {branding?.name || 'FusterAI'}
                                </span>
                            </Link>
                        )}
                        {/* Desktop collapse toggle */}
                        <button
                            onClick={() => setCollapsed(!collapsed)}
                            className="ml-auto hidden md:flex rounded-lg p-2 hover:bg-sidebar-accent/80 transition-colors"
                        >
                            <PanelLeftIcon className="h-4 w-4" />
                        </button>
                        {/* Mobile close button */}
                        <button
                            onClick={() => setMobileOpen(false)}
                            className="ml-auto flex md:hidden rounded-lg p-2 hover:bg-sidebar-accent/80 transition-colors"
                        >
                            <XIcon className="h-4 w-4" />
                        </button>
                    </div>

                    {/* Mailbox switcher */}
                    {!sidebarCollapsed && isCommunicationWorkspace && (mailboxes?.length ?? 0) > 0 && (
                        <div className="relative z-10 px-3 py-2.5 border-b border-sidebar-border/70">
                            <Select value={activeMailbox} onValueChange={switchMailbox}>
                                <SelectTrigger className="w-full bg-transparent border-transparent hover:bg-sidebar-accent/40 text-sm font-medium shadow-none">
                                    <SelectValue placeholder="Select mailbox" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All mailboxes</SelectItem>
                                    {mailboxes?.map((mb) => (
                                        <SelectItem key={mb.id} value={String(mb.id)}>
                                            {mb.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Search */}
                    {!sidebarCollapsed && isCommunicationWorkspace && (
                        <div className="relative z-10 px-3 py-2.5 border-b border-sidebar-border/70">
                            <GlobalSearch />
                        </div>
                    )}

                    {/* Nav */}
                    <nav className="relative z-10 flex-1 overflow-y-auto py-3 px-2.5 space-y-1">
                        {sidebarCollapsed ? (
                            <>
                                <Link href="/dashboard" className={navClass('/dashboard')} title="Dashboard">
                                    <LayoutDashboardIcon className="h-4 w-4 shrink-0" />
                                </Link>
                                <Link href="/conversations" className={navClass('/conversations')} title="Inbox">
                                    <InboxIcon className="h-4 w-4 shrink-0" />
                                </Link>
                                <Link href="/tags" className={navClass('/tags')} title="Tags">
                                    <TagIcon className="h-4 w-4 shrink-0" />
                                </Link>
                                <Link href="/settings/folders" className={navClass('/settings/folders')} title="Folders">
                                    <FolderIcon className="h-4 w-4 shrink-0" />
                                </Link>
                                <Link href="/live-chat" className={navClass('/live-chat')} title="Live Chat">
                                    <MessageSquareIcon className="h-4 w-4 shrink-0" />
                                </Link>
                            </>
                        ) : isCommunicationWorkspace ? (
                            <>
                                <div className="space-y-1">
                                    <button type="button" onClick={() => setInboxOpen((v) => !v)} className={sectionHeaderClass()}>
                                        {inboxOpen ? (
                                            <ChevronDownIcon className="h-3.5 w-3.5" />
                                        ) : (
                                            <ChevronRightIcon className="h-3.5 w-3.5" />
                                        )}
                                        <span className="flex-1">Inbox</span>
                                        {(counts?.open ?? 0) > 0 && (
                                            <span className="text-[10px] text-sidebar-foreground/65">{counts?.open}</span>
                                        )}
                                    </button>
                                    {inboxOpen && (
                                        <div className="ml-4 space-y-0.5 pr-1">
                                            <Link
                                                href="/conversations"
                                                className={cn(
                                                    'flex items-center gap-2 rounded-lg px-2.5 py-1 text-[13px] transition-colors',
                                                    fullPath === '/conversations'
                                                        ? 'text-sidebar-primary font-semibold'
                                                        : 'text-sidebar-foreground/65 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                                                )}
                                            >
                                                <span className="flex-1">All Conversations</span>
                                            </Link>
                                            {inboxFilters.map(({ label, href, count }) => (
                                                <Link
                                                    key={href}
                                                    href={href}
                                                    className={cn(
                                                        'flex items-center gap-2 rounded-lg px-2.5 py-1 text-[13px] transition-colors',
                                                        fullPath === href
                                                            ? 'text-sidebar-primary font-semibold'
                                                            : 'text-sidebar-foreground/65 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                                                    )}
                                                >
                                                    <span className="flex-1">{label}</span>
                                                    {count !== undefined && count > 0 && (
                                                        <span className="text-xs text-sidebar-foreground/45">{count}</span>
                                                    )}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-1 space-y-1">
                                    <button type="button" onClick={() => setTagsOpen((v) => !v)} className={sectionHeaderClass()}>
                                        {tagsOpen ? (
                                            <ChevronDownIcon className="h-3.5 w-3.5" />
                                        ) : (
                                            <ChevronRightIcon className="h-3.5 w-3.5" />
                                        )}
                                        <span className="flex-1">Tags</span>
                                        <Link
                                            href="/tags"
                                            onClick={(e) => e.stopPropagation()}
                                            className="rounded p-0.5 hover:bg-sidebar-accent/60 transition-colors"
                                            title="Manage tags"
                                        >
                                            <PlusIcon className="h-3 w-3" />
                                        </Link>
                                    </button>
                                    {tagsOpen && (
                                        <div className="ml-4 space-y-0.5 pr-1">
                                            {tags && tags.length > 0 ? (
                                                tags.map((tag) => (
                                                    <Link
                                                        key={tag.id}
                                                        href={`/conversations?tag=${tag.id}&status=open`}
                                                        className={cn(
                                                            'flex items-center gap-2 rounded-lg px-2.5 py-1 text-[13px] transition-colors',
                                                            fullPath === `/conversations?tag=${tag.id}&status=open`
                                                                ? 'text-sidebar-primary font-semibold'
                                                                : 'text-sidebar-foreground/65 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                                                        )}
                                                    >
                                                        <span
                                                            className="h-2.5 w-2.5 rounded-full shrink-0"
                                                            style={{ backgroundColor: tag.color }}
                                                        />
                                                        <span className="truncate">{tag.name}</span>
                                                    </Link>
                                                ))
                                            ) : (
                                                <Link
                                                    href="/tags"
                                                    className="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-sidebar-foreground/45 hover:text-sidebar-foreground/65 transition-colors italic"
                                                >
                                                    + Create your first tag
                                                </Link>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-1 space-y-1">
                                    <button type="button" onClick={() => setFoldersOpen((v) => !v)} className={sectionHeaderClass()}>
                                        {foldersOpen ? (
                                            <ChevronDownIcon className="h-3.5 w-3.5" />
                                        ) : (
                                            <ChevronRightIcon className="h-3.5 w-3.5" />
                                        )}
                                        <span className="flex-1">My Folders</span>
                                        <Link
                                            href="/settings/folders"
                                            onClick={(e) => e.stopPropagation()}
                                            className="rounded p-0.5 hover:bg-sidebar-accent/60 transition-colors"
                                            title="Manage folders"
                                        >
                                            <PlusIcon className="h-3 w-3" />
                                        </Link>
                                    </button>
                                    {foldersOpen && (
                                        <div className="ml-4 space-y-0.5 pr-1">
                                            {folders && folders.length > 0 ? (
                                                folders.map((folder) => {
                                                    const href = `/conversations?folder=${folder.id}`;
                                                    return (
                                                        <Link
                                                            key={folder.id}
                                                            href={href}
                                                            className={cn(
                                                                'flex items-center gap-2 rounded-lg px-2.5 py-1 text-[13px] transition-colors',
                                                                fullPath === href
                                                                    ? 'text-sidebar-primary font-semibold'
                                                                    : 'text-sidebar-foreground/65 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                                                            )}
                                                        >
                                                            <span
                                                                className="h-2 w-2 rounded-full shrink-0"
                                                                style={{ backgroundColor: folder.color }}
                                                            />
                                                            <span className="flex-1 truncate">{folder.name}</span>
                                                            {(folder.open_count ?? 0) > 0 && (
                                                                <span className="text-xs text-sidebar-foreground/45">
                                                                    {folder.open_count}
                                                                </span>
                                                            )}
                                                        </Link>
                                                    );
                                                })
                                            ) : (
                                                <Link
                                                    href="/settings/folders"
                                                    className="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-sidebar-foreground/45 hover:text-sidebar-foreground/65 transition-colors italic"
                                                >
                                                    + Create your first folder
                                                </Link>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* My Views */}
                                <div className="mt-1 space-y-1">
                                    <button type="button" onClick={() => setViewsOpen((v) => !v)} className={sectionHeaderClass()}>
                                        {viewsOpen ? (
                                            <ChevronDownIcon className="h-3.5 w-3.5" />
                                        ) : (
                                            <ChevronRightIcon className="h-3.5 w-3.5" />
                                        )}
                                        <span className="flex-1">My Views</span>
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onCreateView?.();
                                            }}
                                            className="rounded p-0.5 hover:bg-sidebar-accent/60 transition-colors"
                                            title="New view"
                                        >
                                            <PlusIcon className="h-3 w-3" />
                                        </button>
                                    </button>
                                    {viewsOpen && (
                                        <div className="ml-4 space-y-0.5 pr-1">
                                            {customViews && customViews.length > 0 ? (
                                                customViews.map((view) => {
                                                    const href = `/conversations?view=${view.id}`;
                                                    return (
                                                        <Link
                                                            key={view.id}
                                                            href={href}
                                                            className={cn(
                                                                'flex items-center gap-2 rounded-lg px-2.5 py-1 text-[13px] transition-colors',
                                                                fullPath === href
                                                                    ? 'text-sidebar-primary font-semibold'
                                                                    : 'text-sidebar-foreground/65 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                                                            )}
                                                        >
                                                            <span
                                                                className="h-2 w-2 rounded-full shrink-0"
                                                                style={{ backgroundColor: view.color }}
                                                            />
                                                            <span className="flex-1 truncate">{view.name}</span>
                                                            {view.is_shared && (
                                                                <span className="text-[9px] text-sidebar-foreground/35 uppercase tracking-wide shrink-0">
                                                                    shared
                                                                </span>
                                                            )}
                                                        </Link>
                                                    );
                                                })
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => onCreateView?.()}
                                                    className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-sidebar-foreground/45 hover:text-sidebar-foreground/65 transition-colors italic"
                                                >
                                                    + Create your first view
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="my-1.5 h-px bg-sidebar-muted/75" />
                                <Link href="/live-chat" className={navClass('/live-chat')}>
                                    <MessageSquareIcon className="h-4 w-4 shrink-0" />
                                    <span>Live Chat</span>
                                </Link>
                            </>
                        ) : (
                            <div className="space-y-1">
                                <Link href="/dashboard" className={navClass('/dashboard')}>
                                    <LayoutDashboardIcon className="h-4 w-4 shrink-0" />
                                    <span>Dashboard</span>
                                </Link>
                                <p className="px-2.5 pt-3 pb-1 text-[11px] font-semibold text-sidebar-foreground/50 uppercase tracking-[0.12em]">
                                    Communication
                                </p>
                                <Link href="/conversations" className={navClass('/conversations')}>
                                    <InboxIcon className="h-4 w-4 shrink-0" />
                                    <span>Inbox</span>
                                </Link>
                                <Link href="/live-chat" className={navClass('/live-chat')}>
                                    <MessageSquareIcon className="h-4 w-4 shrink-0" />
                                    <span>Live Chat</span>
                                </Link>
                            </div>
                        )}
                    </nav>

                    {/* Module slot: inject extra sidebar content below nav */}
                    <SlotRenderer name="app.sidebar.bottom" />
                </aside>

                {/* Main content */}
                <div className={cn('flex-1 flex flex-col min-w-0 bg-background', fullHeight ? 'overflow-hidden' : 'overflow-y-auto')}>
                    <header className="sticky top-0 z-20 flex items-center border-b border-border/60 bg-background/95 h-16 px-4 md:px-5 backdrop-blur-sm shrink-0">
                        <div className="flex w-full items-center justify-between gap-4">
                            {/* Mobile hamburger */}
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="flex md:hidden items-center justify-center h-9 w-9 rounded-lg hover:bg-muted/70 transition-colors"
                            >
                                <MenuIcon className="h-5 w-5" />
                            </button>

                            <nav className="hidden items-center gap-0.5 lg:flex">
                                {/* Inbox ▾ */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                                inboxNavActive
                                                    ? 'text-primary font-semibold'
                                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            <MailboxIcon className="h-3.5 w-3.5" />
                                            <span>Inbox</span>
                                            <ChevronDownIcon className="h-3 w-3 opacity-50" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-52 border-0 bg-popover/95 shadow-xl backdrop-blur">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/mailboxes"
                                                    className={cn('w-full', path.startsWith('/mailboxes') && 'text-primary')}
                                                >
                                                    <MailboxIcon className="h-4 w-4" />
                                                    <span>Mailboxes</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href="/tags" className={cn('w-full', path.startsWith('/tags') && 'text-primary')}>
                                                    <TagIcon className="h-4 w-4" />
                                                    <span>Tags</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/folders"
                                                    className={cn('w-full', path.startsWith('/settings/folders') && 'text-primary')}
                                                >
                                                    <FolderIcon className="h-4 w-4" />
                                                    <span>Folders</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/canned-responses"
                                                    className={cn(
                                                        'w-full',
                                                        path.startsWith('/settings/canned-responses') && 'text-primary',
                                                    )}
                                                >
                                                    <MessageSquareTextIcon className="h-4 w-4" />
                                                    <span>Canned Responses</span>
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Customers */}
                                <Link
                                    href="/customers"
                                    className={cn(
                                        'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                        customersNavActive
                                            ? 'text-primary font-semibold'
                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                    )}
                                >
                                    <UsersIcon className="h-3.5 w-3.5" />
                                    <span>Customers</span>
                                </Link>

                                {/* Analytics */}
                                <Link
                                    href="/reports"
                                    className={cn(
                                        'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                        analyticsNavActive
                                            ? 'text-primary font-semibold'
                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                    )}
                                >
                                    <BarChartIcon className="h-3.5 w-3.5" />
                                    <span>Analytics</span>
                                </Link>

                                {/* AI ▾ */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                                aiNavActive
                                                    ? 'text-primary font-semibold'
                                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            <BrainIcon className="h-3.5 w-3.5" />
                                            <span>AI</span>
                                            <ChevronDownIcon className="h-3 w-3 opacity-50" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-52 border-0 bg-popover/95 shadow-xl backdrop-blur">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/ai"
                                                    className={cn('w-full', path.startsWith('/settings/ai') && 'text-primary')}
                                                >
                                                    <BrainIcon className="h-4 w-4" />
                                                    <span>AI Config</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/ai/knowledge-base"
                                                    className={cn('w-full', path.startsWith('/ai/') && 'text-primary')}
                                                >
                                                    <BookOpenIcon className="h-4 w-4" />
                                                    <span>Knowledge Base</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/automation"
                                                    className={cn('w-full', path.startsWith('/automation') && 'text-primary')}
                                                >
                                                    <BotIcon className="h-4 w-4" />
                                                    <span>Automation</span>
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Customization ▾ */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                                customNavActive
                                                    ? 'text-primary font-semibold'
                                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            <MonitorIcon className="h-3.5 w-3.5" />
                                            <span>Customization</span>
                                            <ChevronDownIcon className="h-3 w-3 opacity-50" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-52 border-0 bg-popover/95 shadow-xl backdrop-blur">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/appearance"
                                                    className={cn('w-full', path.startsWith('/settings/appearance') && 'text-primary')}
                                                >
                                                    <MonitorIcon className="h-4 w-4" />
                                                    <span>Appearance</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/live-chat"
                                                    className={cn('w-full', path.startsWith('/settings/live-chat') && 'text-primary')}
                                                >
                                                    <MessageSquareIcon className="h-4 w-4" />
                                                    <span>Live Chat Widget</span>
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Settings ▾ */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium whitespace-nowrap transition-all',
                                                settingsNavActive
                                                    ? 'text-primary font-semibold'
                                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            <SettingsIcon className="h-3.5 w-3.5" />
                                            <span>Settings</span>
                                            <ChevronDownIcon className="h-3 w-3 opacity-50" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-52 border-0 bg-popover/95 shadow-xl backdrop-blur">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem asChild>
                                                <Link href="/settings" className={cn('w-full', path === '/settings' && 'text-primary')}>
                                                    <SettingsIcon className="h-4 w-4" />
                                                    <span>General</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/users"
                                                    className={cn('w-full', path.startsWith('/settings/users') && 'text-primary')}
                                                >
                                                    <UsersIcon className="h-4 w-4" />
                                                    <span>Team</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/modules"
                                                    className={cn('w-full', path.startsWith('/settings/modules') && 'text-primary')}
                                                >
                                                    <PuzzleIcon className="h-4 w-4" />
                                                    <span>Modules</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/email"
                                                    className={cn('w-full', path.startsWith('/settings/email') && 'text-primary')}
                                                >
                                                    <MailIcon className="h-4 w-4" />
                                                    <span>Email</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/api-keys"
                                                    className={cn('w-full', path.startsWith('/settings/api-keys') && 'text-primary')}
                                                >
                                                    <KeyIcon className="h-4 w-4" />
                                                    <span>API Keys</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href="/settings/audit-log"
                                                    className={cn('w-full', path.startsWith('/settings/audit-log') && 'text-primary')}
                                                >
                                                    <ClipboardListIcon className="h-4 w-4" />
                                                    <span>Audit Log</span>
                                                </Link>
                                            </DropdownMenuItem>
                                            {(usePage<PageProps>().props as any).activeModules?.includes('SlaManager') && (
                                                <>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href="/settings/sla"
                                                            className={cn('w-full', path === '/settings/sla' && 'text-primary')}
                                                        >
                                                            <ClockIcon className="h-4 w-4" />
                                                            <span>SLA Policies</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href="/settings/sla/report"
                                                            className={cn(
                                                                'w-full',
                                                                path.startsWith('/settings/sla/report') && 'text-primary',
                                                            )}
                                                        >
                                                            <BarChartIcon className="h-4 w-4" />
                                                            <span>SLA Report</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </>
                                            )}
                                            {(usePage<PageProps>().props as any).activeModules?.includes('ConversationRouting') && (
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href="/settings/routing"
                                                        className={cn('w-full', path.startsWith('/settings/routing') && 'text-primary')}
                                                    >
                                                        <ClockIcon className="h-4 w-4" />
                                                        <span>Routing</span>
                                                    </Link>
                                                </DropdownMenuItem>
                                            )}
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </nav>

                            <div className="ml-auto flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={cycleTheme}
                                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl text-foreground transition-colors hover:bg-muted/70"
                                    title={`Theme: ${appearance.mode}`}
                                >
                                    {appearance.mode === 'light' && <SunIcon className="h-4 w-4" />}
                                    {appearance.mode === 'dark' && <MoonIcon className="h-4 w-4" />}
                                    {appearance.mode === 'system' && <MonitorIcon className="h-4 w-4" />}
                                </button>

                                <NotificationBell />

                                {/* Avatar — profile + status + sign out */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="inline-flex h-10 items-center gap-2 rounded-xl px-2.5 text-left transition-colors hover:bg-muted/70"
                                        >
                                            <div className="relative shrink-0">
                                                <Avatar className="size-8">
                                                    <AvatarImage src={auth.user?.avatar ?? undefined} alt={auth.user?.name ?? 'User'} />
                                                    <AvatarFallback>{getInitials(auth.user?.name)}</AvatarFallback>
                                                </Avatar>
                                                <StatusDot status={currentUserStatus} />
                                            </div>
                                            <div className="hidden min-w-0 sm:block">
                                                <p className="truncate text-sm font-semibold">{auth.user?.name}</p>
                                                <p className="truncate text-xs text-muted-foreground capitalize">{auth.user?.role}</p>
                                            </div>
                                            <ChevronDownIcon className="h-4 w-4 text-muted-foreground" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-52 border-0 bg-popover/95 shadow-xl backdrop-blur">
                                        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                            {auth.user?.name} · <span className="capitalize">{auth.user?.role}</span>
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuGroup>
                                            {(Object.keys(AGENT_STATUS_LABELS) as AgentStatus[]).map((s) => (
                                                <DropdownMenuItem
                                                    key={s}
                                                    onSelect={() => router.patch('/profile/status', { status: s })}
                                                    className="gap-2"
                                                >
                                                    <span className={cn('size-2 rounded-full shrink-0', AGENT_STATUS_COLORS[s])} />
                                                    <span>{AGENT_STATUS_LABELS[s]}</span>
                                                    {currentUserStatus === s && (
                                                        <span className="ml-auto text-[10px] text-primary font-medium">✓</span>
                                                    )}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuGroup>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href="/profile">
                                                <UserIcon className="h-4 w-4" />
                                                <span>My Profile</span>
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onSelect={(e) => {
                                                e.preventDefault();
                                                router.post('/logout');
                                            }}
                                            className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                        >
                                            <LogOutIcon className="h-4 w-4" />
                                            <span>Sign out</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </header>

                    <div className={cn('min-h-0 flex-1', fullHeight ? 'flex overflow-hidden' : '')}>{children}</div>
                </div>
            </div>
        </div>
    );
}
