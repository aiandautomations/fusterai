import React, { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { BellIcon, CheckCheckIcon, InboxIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface AppNotification {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        type: string;
        conversation_id: number;
        subject: string;
        preview?: string;
        url: string;
    };
}

interface NotificationsResponse {
    data: AppNotification[];
    total: number;
}

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)   return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function typeLabel(type: string): string {
    switch (type) {
        case 'assigned':   return 'Assigned to you';
        case 'new_reply':  return 'New customer reply';
        case 'follower':   return 'Reply on followed ticket';
        default:           return 'Notification';
    }
}

export default function NotificationBell() {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);

    // Fetch notifications when panel opens
    useEffect(() => {
        if (!open) return;
        setLoading(true);
        fetch('/notifications', { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((res: NotificationsResponse) => {
                setNotifications(res.data);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [open]);

    // Fetch unread count on mount + poll every 60s
    useEffect(() => {
        function fetchCount() {
            fetch('/notifications', { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((res: NotificationsResponse) => {
                    setUnreadCount(res.data.filter((n) => !n.read_at).length);
                })
                .catch(() => {});
        }

        fetchCount();
        const interval = setInterval(fetchCount, 60_000);
        return () => clearInterval(interval);
    }, []);

    // Real-time: listen for new notifications via Reverb
    useEffect(() => {
        const channelName = `App.Models.User.${auth.user?.id}`;
        const ch = window.Echo?.private(channelName);
        ch?.notification((notification: AppNotification) => {
            setUnreadCount((c) => c + 1);
            setNotifications((prev) => [notification, ...prev]);
        });
        return () => window.Echo?.leave(channelName);
    }, [auth.user?.id]);

    // Close on outside click
    useEffect(() => {
        if (!open) return;
        function handleClick(e: MouseEvent) {
            if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, [open]);

    function markAllRead() {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        }).then(() => {
            setUnreadCount(0);
            setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
        });
    }

    function markRead(id: string) {
        fetch(`/notifications/${id}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        }).then(() => {
            setUnreadCount((c) => Math.max(0, c - 1));
            setNotifications((prev) =>
                prev.map((n) => n.id === id ? { ...n, read_at: new Date().toISOString() } : n),
            );
        });
    }

    function handleClick(notification: AppNotification) {
        if (!notification.read_at) markRead(notification.id);
        setOpen(false);
        router.visit(notification.data.url);
    }

    return (
        <div className="relative" ref={panelRef}>
            {/* Bell button */}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-foreground transition-colors hover:bg-muted/70"
                title="Notifications"
            >
                <BellIcon className="h-4 w-4" />
                {unreadCount > 0 && (
                    <span className="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-white leading-none">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {/* Dropdown panel */}
            {open && (
                <div className="absolute right-0 top-full mt-2 w-80 rounded-xl border border-border bg-popover shadow-2xl z-50 flex flex-col overflow-hidden">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                        <span className="text-sm font-semibold">Notifications</span>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllRead}
                                className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <CheckCheckIcon className="h-3.5 w-3.5" />
                                Mark all read
                            </button>
                        )}
                    </div>

                    {/* List */}
                    <div className="max-h-[420px] overflow-y-auto">
                        {loading ? (
                            <div className="space-y-3 p-4">
                                {[1, 2, 3].map((i) => (
                                    <div key={i} className="space-y-1.5">
                                        <div className="h-3 w-3/4 rounded bg-muted animate-pulse" />
                                        <div className="h-2.5 w-1/2 rounded bg-muted animate-pulse" />
                                    </div>
                                ))}
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
                                <InboxIcon className="h-8 w-8 text-muted-foreground/30" />
                                <p className="text-sm text-muted-foreground">No notifications yet</p>
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <button
                                    key={n.id}
                                    type="button"
                                    onClick={() => handleClick(n)}
                                    className={cn(
                                        'w-full text-left px-4 py-3 border-b border-border/50 last:border-0 transition-colors hover:bg-muted/50',
                                        !n.read_at && 'bg-primary/5',
                                    )}
                                >
                                    <div className="flex items-start gap-2.5">
                                        {!n.read_at && (
                                            <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" />
                                        )}
                                        <div className={cn('min-w-0 flex-1', n.read_at && 'pl-4')}>
                                            <p className="text-xs font-medium text-muted-foreground mb-0.5">
                                                {typeLabel(n.data.type)}
                                            </p>
                                            <p className="text-sm font-medium truncate">{n.data.subject}</p>
                                            {n.data.preview && (
                                                <p className="text-xs text-muted-foreground truncate mt-0.5">
                                                    {n.data.preview}
                                                </p>
                                            )}
                                            <p className="text-[11px] text-muted-foreground/60 mt-1">
                                                {timeAgo(n.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
