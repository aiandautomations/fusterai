import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import type { CustomView, Mailbox, Tag } from '@/types';

const COLOR_OPTIONS = [
    '#6366f1',
    '#8b5cf6',
    '#ec4899',
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#14b8a6',
    '#3b82f6',
    '#06b6d4',
    '#64748b',
    '#1e293b',
];

interface ViewBuilderModalProps {
    onClose: () => void;
    mailboxes: Mailbox[];
    tags: Tag[];
    agents: { id: number; name: string }[];
    editingView?: CustomView | null;
    /** Pre-fill filters from current URL (save-as mode). */
    currentFilters?: Record<string, string | undefined>;
    userRole: string;
}

export default function ViewBuilderModal({
    onClose,
    mailboxes,
    tags,
    agents,
    editingView,
    currentFilters,
    userRole,
}: ViewBuilderModalProps) {
    const canShare = ['super_admin', 'admin', 'manager'].includes(userRole);

    const src = editingView?.filters ?? {};
    const cf = currentFilters ?? {};

    const [name, setName] = useState(editingView?.name ?? '');
    const [color, setColor] = useState(editingView?.color ?? '#6366f1');
    const [isShared, setIsShared] = useState(editingView?.is_shared ?? false);
    const [status, setStatus] = useState(src.status ?? cf.status ?? '');
    const [assigned, setAssigned] = useState(src.assigned ?? cf.assigned ?? '');
    const [mailboxId, setMailboxId] = useState(src.mailbox_id ? String(src.mailbox_id) : (cf.mailbox ?? ''));
    const [tagId, setTagId] = useState(src.tag_id ? String(src.tag_id) : (cf.tag ?? ''));
    const [priority, setPriority] = useState(src.priority ?? cf.priority ?? '');
    const [channelType, setChannelType] = useState(src.channel_type ?? '');
    const [submitting, setSubmitting] = useState(false);

    function buildFilters(): CustomView['filters'] {
        const f: CustomView['filters'] = {};
        if (status) f.status = status;
        if (assigned) f.assigned = assigned;
        if (mailboxId) f.mailbox_id = Number(mailboxId);
        if (tagId) f.tag_id = Number(tagId);
        if (priority) f.priority = priority;
        if (channelType) f.channel_type = channelType;
        return f;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!name.trim()) return;
        setSubmitting(true);

        const payload = { name: name.trim(), color, is_shared: isShared, filters: buildFilters() };

        if (editingView) {
            router.patch(`/views/${editingView.id}`, payload, {
                onSuccess: () => onClose(),
                onError: () => setSubmitting(false),
            });
        } else {
            router.post('/views', payload, {
                onSuccess: () => onClose(),
                onError: () => setSubmitting(false),
            });
        }
    }

    const selectClass =
        'w-full border border-input rounded-md px-3 py-2 text-sm bg-background focus:outline-none focus:ring-1 focus:ring-ring';
    const labelClass = 'text-xs font-medium text-muted-foreground';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/40 backdrop-blur-sm"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div className="bg-card rounded-xl shadow-2xl border border-border w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
                    <h2 className="font-semibold text-base">{editingView ? 'Edit View' : 'New View'}</h2>
                    <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground transition-colors">
                        <XIcon className="h-4 w-4" />
                    </button>
                </div>

                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="px-5 py-4 space-y-4 overflow-y-auto flex-1">
                        {/* Name */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Name *</label>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="e.g. Urgent unassigned"
                                className={selectClass}
                                required
                                maxLength={100}
                                autoFocus
                            />
                        </div>

                        {/* Color */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Color</label>
                            <div className="flex flex-wrap gap-2">
                                {COLOR_OPTIONS.map((c) => (
                                    <button
                                        key={c}
                                        type="button"
                                        onClick={() => setColor(c)}
                                        className={cn(
                                            'h-6 w-6 rounded-full transition-all ring-offset-background',
                                            color === c && 'ring-2 ring-offset-2 ring-ring',
                                        )}
                                        style={{ backgroundColor: c }}
                                    />
                                ))}
                            </div>
                        </div>

                        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/60 pt-1">Filters</p>

                        {/* Status */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Status</label>
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className={selectClass}>
                                <option value="">Any</option>
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="snoozed">Snoozed</option>
                                <option value="closed">Closed</option>
                                <option value="spam">Spam</option>
                            </select>
                        </div>

                        {/* Assigned */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Assigned to</label>
                            <select value={assigned} onChange={(e) => setAssigned(e.target.value)} className={selectClass}>
                                <option value="">Anyone</option>
                                <option value="me">Me</option>
                                <option value="none">Unassigned</option>
                                {agents.map((a) => (
                                    <option key={a.id} value={String(a.id)}>
                                        {a.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Mailbox */}
                        {mailboxes.length > 0 && (
                            <div className="space-y-1.5">
                                <label className={labelClass}>Mailbox</label>
                                <select value={mailboxId} onChange={(e) => setMailboxId(e.target.value)} className={selectClass}>
                                    <option value="">Any</option>
                                    {mailboxes.map((m) => (
                                        <option key={m.id} value={String(m.id)}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Tag */}
                        {tags.length > 0 && (
                            <div className="space-y-1.5">
                                <label className={labelClass}>Tag</label>
                                <select value={tagId} onChange={(e) => setTagId(e.target.value)} className={selectClass}>
                                    <option value="">Any</option>
                                    {tags.map((t) => (
                                        <option key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Priority */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Priority</label>
                            <select value={priority} onChange={(e) => setPriority(e.target.value)} className={selectClass}>
                                <option value="">Any</option>
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        {/* Channel */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Channel</label>
                            <select value={channelType} onChange={(e) => setChannelType(e.target.value)} className={selectClass}>
                                <option value="">Any</option>
                                <option value="email">Email</option>
                                <option value="chat">Live Chat</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="slack">Slack</option>
                                <option value="api">API</option>
                            </select>
                        </div>

                        {/* Share toggle (managers+) */}
                        {canShare && (
                            <div className="flex items-center justify-between pt-1">
                                <div>
                                    <p className="text-sm font-medium">Share with workspace</p>
                                    <p className="text-xs text-muted-foreground">All agents will see this view.</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked={isShared}
                                    onClick={() => setIsShared((v) => !v)}
                                    className={cn(
                                        'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                                        isShared ? 'bg-primary' : 'bg-muted-foreground/30',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform',
                                            isShared ? 'translate-x-4' : 'translate-x-1',
                                        )}
                                    />
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-2 px-5 py-4 border-t border-border shrink-0">
                        <Button type="button" variant="ghost" size="sm" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" disabled={submitting || !name.trim()}>
                            {submitting ? 'Saving…' : editingView ? 'Save Changes' : 'Create View'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
