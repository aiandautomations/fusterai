import React, { useState } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn, getInitials } from '@/lib/utils';
import { type PageProps } from '@/types';
import StatusDot from '@/Components/StatusDot';
import { type AgentStatus } from '@/lib/agentStatus';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    MoreHorizontalIcon,
    MailIcon,
    ShieldIcon,
    UserPlusIcon,
    SearchIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CheckIcon,
} from 'lucide-react';

interface MailboxSummary {
    id: number;
    name: string;
}
interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    avatar?: string;
    mailboxes: MailboxSummary[];
}
interface Mailbox {
    id: number;
    name: string;
    email: string;
}
interface Props {
    users: User[];
    mailboxes: Mailbox[];
}

// ── Role config ───────────────────────────────────────────────────────────────

const ROLES: Record<string, { label: string; dot: string; badge: string }> = {
    super_admin: { label: 'Super Admin', dot: 'bg-destructive', badge: 'bg-destructive/10 text-destructive' },
    admin: { label: 'Admin', dot: 'bg-primary', badge: 'bg-primary/10 text-primary' },
    manager: { label: 'Manager', dot: 'bg-info', badge: 'bg-info/10 text-info' },
    agent: { label: 'Agent', dot: 'bg-muted-foreground/50', badge: 'bg-muted text-muted-foreground' },
};

function RoleBadge({ role }: { role: string }) {
    const r = ROLES[role] ?? ROLES.agent;
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold', r.badge)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', r.dot)} />
            {r.label}
        </span>
    );
}

// ── Edit user inline form ─────────────────────────────────────────────────────

function EditUserForm({ user, onCancel }: { user: User; onCancel: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({ name: user.name, role: user.role });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/settings/users/${user.id}`, { onSuccess: onCancel });
    }

    return (
        <form onSubmit={submit} className="px-4 pb-4 pt-2 bg-muted/30 border-t border-border">
            <div className="flex items-end gap-3">
                <div className="flex-1 space-y-1">
                    <Label className="text-xs">Name</Label>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required className="h-8 text-sm" />
                    {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>
                <div className="w-36 space-y-1">
                    <Label className="text-xs">Role</Label>
                    <select
                        className="w-full h-8 border rounded-md px-2 text-sm bg-background"
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value)}
                    >
                        <option value="agent">Agent</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <Button type="submit" size="sm" disabled={processing} className="h-8">
                    Save
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onCancel} className="h-8">
                    Cancel
                </Button>
            </div>
        </form>
    );
}

// ── Mailbox access inline panel ───────────────────────────────────────────────

function MailboxAccessPanel({ user, allMailboxes }: { user: User; allMailboxes: Mailbox[] }) {
    const [selected, setSelected] = useState<number[]>(user.mailboxes.map((mb) => mb.id));
    const [saving, setSaving] = useState(false);
    const isDirty = JSON.stringify(selected.sort()) !== JSON.stringify(user.mailboxes.map((m) => m.id).sort());

    function toggle(id: number) {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function save() {
        setSaving(true);
        router.patch(`/settings/users/${user.id}/mailboxes`, { mailbox_ids: selected }, { onFinish: () => setSaving(false) });
    }

    return (
        <div className="px-4 pb-4 pt-2 bg-muted/30 border-t border-border space-y-3">
            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Mailbox access</p>
            {allMailboxes.length === 0 ? (
                <p className="text-xs text-muted-foreground">No mailboxes configured.</p>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    {allMailboxes.map((mb) => {
                        const on = selected.includes(mb.id);
                        return (
                            <button
                                key={mb.id}
                                type="button"
                                onClick={() => toggle(mb.id)}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-lg border px-3 py-2 text-left text-sm transition-all',
                                    on
                                        ? 'border-primary/30 bg-primary/5 text-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:border-border/80 hover:bg-muted/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors',
                                        on ? 'border-primary bg-primary' : 'border-border bg-background',
                                    )}
                                >
                                    {on && <CheckIcon className="h-2.5 w-2.5 text-primary-foreground" />}
                                </span>
                                <span className="flex-1 min-w-0">
                                    <span className="block text-[12px] font-medium truncate">{mb.name}</span>
                                    <span className="block text-[11px] text-muted-foreground truncate">{mb.email}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
            {isDirty && (
                <Button size="sm" disabled={saving} onClick={save} className="h-7 text-xs">
                    {saving ? 'Saving…' : 'Save access'}
                </Button>
            )}
        </div>
    );
}

// ── User row ──────────────────────────────────────────────────────────────────

function UserRow({
    user,
    isSelf,
    editingId,
    setEditingId,
    mailboxPanel,
    setMailboxPanel,
    allMailboxes,
    onDelete,
}: {
    user: User;
    isSelf: boolean;
    editingId: number | null;
    setEditingId: (id: number | null) => void;
    mailboxPanel: number | null;
    setMailboxPanel: (id: number | null) => void;
    allMailboxes: Mailbox[];
    onDelete: (user: User) => void;
}) {
    const isEditing = editingId === user.id;
    const showMailbox = mailboxPanel === user.id;
    const { agentStatuses } = usePage<PageProps & { agentStatuses: Record<number, string> }>().props;
    const agentStatus = (agentStatuses?.[user.id] ?? 'offline') as AgentStatus;

    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-background overflow-hidden transition-shadow',
                (isEditing || showMailbox) && 'shadow-sm',
            )}
        >
            <div className="flex items-center gap-4 px-4 py-3.5">
                {/* Avatar */}
                <div className="relative shrink-0">
                    <Avatar className="size-9">
                        <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                        <AvatarFallback className="text-xs font-bold bg-primary/10 text-primary">{getInitials(user.name)}</AvatarFallback>
                    </Avatar>
                    <StatusDot status={agentStatus} />
                </div>

                {/* Name / email */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-[13.5px] font-semibold text-foreground">{user.name}</span>
                        <RoleBadge role={user.role} />
                        {isSelf && (
                            <span className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide bg-muted px-1.5 py-0.5 rounded-full">
                                You
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5 truncate">{user.email}</p>
                </div>

                {/* Mailbox access count */}
                <button
                    type="button"
                    onClick={() => setMailboxPanel(showMailbox ? null : user.id)}
                    className={cn(
                        'hidden sm:flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all border',
                        showMailbox
                            ? 'border-primary/30 bg-primary/5 text-primary'
                            : 'border-border text-muted-foreground hover:text-foreground hover:bg-muted/40',
                    )}
                >
                    <MailIcon className="h-3 w-3" />
                    {user.mailboxes.length > 0 ? `${user.mailboxes.length} mailbox${user.mailboxes.length !== 1 ? 'es' : ''}` : 'No access'}
                    <ChevronDownIcon className={cn('h-3 w-3 transition-transform', showMailbox && 'rotate-180')} />
                </button>

                {/* Actions menu */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted/60 hover:text-foreground transition-colors">
                            <MoreHorizontalIcon className="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuItem onClick={() => setEditingId(isEditing ? null : user.id)}>
                            {isEditing ? 'Cancel editing' : 'Edit member'}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setMailboxPanel(showMailbox ? null : user.id)}>
                            Manage mailbox access
                        </DropdownMenuItem>
                        {!isSelf && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={() => onDelete(user)}>
                                    Remove member
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {isEditing && <EditUserForm user={user} onCancel={() => setEditingId(null)} />}
            {showMailbox && !isEditing && <MailboxAccessPanel user={user} allMailboxes={allMailboxes} />}
        </div>
    );
}

// ── Invite form ───────────────────────────────────────────────────────────────

function InviteForm() {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({ name: '', email: '', role: 'agent' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/users', { onSuccess: () => reset() });
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-3">
                <div className="space-y-1.5">
                    <Label className="text-[13px]">Full name</Label>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Jane Smith" required />
                    {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label className="text-[13px]">Email address</Label>
                    <Input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="jane@company.com"
                        required
                    />
                    {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label className="text-[13px]">Role</Label>
                    <div className="grid grid-cols-2 gap-2">
                        {(['agent', 'manager', 'admin'] as const).map((role) => {
                            const r = ROLES[role];
                            return (
                                <button
                                    key={role}
                                    type="button"
                                    onClick={() => setData('role', role)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-all',
                                        data.role === role
                                            ? 'border-primary/30 bg-primary/5 text-foreground shadow-sm'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    )}
                                >
                                    <span className={cn('h-2 w-2 rounded-full shrink-0', r.dot)} />
                                    <span className="text-[12px] font-medium">{r.label}</span>
                                    {data.role === role && <CheckIcon className="h-3 w-3 ml-auto text-primary" />}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>

            <div className="rounded-lg bg-muted/50 border border-border px-3 py-2.5 text-xs text-muted-foreground">
                An invite email will be sent so the member can set their own password.
            </div>

            <Button type="submit" disabled={processing} className="w-full gap-2">
                <UserPlusIcon className="h-4 w-4" />
                {processing ? 'Sending invite…' : 'Send invite'}
            </Button>
        </form>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function SettingsUsers({ users, mailboxes }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [editingId, setEditingId] = useState<number | null>(null);
    const [mailboxPanel, setMailboxPanel] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    function confirmDelete(user: User) {
        if (!confirm(`Remove ${user.name} from the workspace? This cannot be undone.`)) return;
        router.delete(`/settings/users/${user.id}`);
    }

    const filtered = users.filter(
        (u) => u.name.toLowerCase().includes(search.toLowerCase()) || u.email.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <AppLayout>
            <div className="w-full px-6 py-8 space-y-6 max-w-6xl">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Team</h1>
                    <p className="text-sm text-muted-foreground mt-1">Manage members, roles, and mailbox access across your workspace.</p>
                </div>

                <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_340px]">
                    {/* ── Left: Team list ── */}
                    <div className="space-y-4">
                        {/* Toolbar */}
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1">
                                <SearchIcon className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search members…"
                                    className="pl-9 h-9"
                                />
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/60 px-3 py-2 rounded-lg border border-border">
                                <ShieldIcon className="h-3.5 w-3.5" />
                                <span>
                                    {users.length} member{users.length !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>

                        {/* Members list */}
                        {filtered.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border py-16 text-center">
                                <p className="text-sm text-muted-foreground">
                                    {search ? `No members match "${search}"` : 'No team members yet.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {filtered.map((user) => (
                                    <UserRow
                                        key={user.id}
                                        user={user}
                                        isSelf={user.id === auth.user?.id}
                                        editingId={editingId}
                                        setEditingId={setEditingId}
                                        mailboxPanel={mailboxPanel}
                                        setMailboxPanel={setMailboxPanel}
                                        allMailboxes={mailboxes}
                                        onDelete={confirmDelete}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* ── Right: Invite ── */}
                    <div>
                        <div className="rounded-xl border border-border bg-card p-5 space-y-1 sticky top-6">
                            <div className="mb-5">
                                <h2 className="text-[15px] font-semibold">Invite a member</h2>
                                <p className="text-xs text-muted-foreground mt-0.5">They'll receive an email to join your workspace.</p>
                            </div>
                            <InviteForm />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
