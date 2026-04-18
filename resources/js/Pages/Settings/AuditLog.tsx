import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Input } from '@/Components/ui/input';
import { ChevronDownIcon } from 'lucide-react';
import { type Paginated } from '@/types';

interface Causer {
    id: number;
    name: string;
    email: string;
}
interface LogEntry {
    id: number;
    log_name: string;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer: Causer | null;
    properties: Record<string, unknown>;
    created_at: string;
}

interface Props {
    logs: Paginated<LogEntry>;
    days: number;
    search: string;
}

function formatSubject(type: string | null) {
    if (!type) return '—';
    return type.split('\\').pop() ?? type;
}

function formatDate(d: string) {
    return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function DiffViewer({ properties }: { properties: Record<string, unknown> }) {
    const [open, setOpen] = React.useState(false);
    const attrs = (properties?.attributes ?? properties?.old) as Record<string, unknown> | null;
    if (!attrs || Object.keys(attrs).length === 0) return <span className="text-xs text-muted-foreground/50">—</span>;

    return (
        <div>
            <button
                onClick={() => setOpen((v) => !v)}
                className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
                <ChevronDownIcon className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} />
                {open ? 'Hide' : 'Show'} changes
            </button>
            {open && (
                <pre className="mt-1.5 rounded-lg bg-muted px-3 py-2 text-[11px] font-mono overflow-x-auto max-w-xs whitespace-pre-wrap">
                    {JSON.stringify(attrs, null, 2)}
                </pre>
            )}
        </div>
    );
}

export default function AuditLog({ logs, days, search }: Props) {
    const [searchVal, setSearchVal] = React.useState(search);

    function applyFilters(opts: { days?: number; search?: string; page?: number } = {}) {
        router.get(
            '/settings/audit-log',
            {
                days: opts.days ?? days,
                search: opts.search ?? searchVal,
                page: opts.page,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout title="Audit Log">
            <div className="w-full px-6 py-8 space-y-8">
                <div className="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Audit Log</h1>
                        <p className="text-sm text-muted-foreground mt-1">Track all changes made by your team.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            placeholder="Search…"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            className="w-48"
                        />
                        <Select value={String(days)} onValueChange={(v) => applyFilters({ days: Number(v) })}>
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="7">Last 7 days</SelectItem>
                                <SelectItem value="30">Last 30 days</SelectItem>
                                <SelectItem value="90">Last 90 days</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-xl border border-border/80 bg-card/75 overflow-hidden overflow-x-auto">
                    {logs.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground px-5 py-12 text-center">No activity in the selected period.</p>
                    ) : (
                        <table className="w-full text-sm min-w-[640px]">
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/30">
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-[0.1em] whitespace-nowrap">
                                        Date
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-[0.1em]">
                                        User
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-[0.1em]">
                                        Event
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-[0.1em]">
                                        Subject
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-[0.1em]">
                                        Changes
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/50">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                            {formatDate(log.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.causer ? (
                                                <div>
                                                    <p className="font-medium">{log.causer.name}</p>
                                                    <p className="text-xs text-muted-foreground">{log.causer.email}</p>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">System</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 capitalize">{log.description}</td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatSubject(log.subject_type)}
                                            {log.subject_id ? <span className="text-xs"> #{log.subject_id}</span> : null}
                                        </td>
                                        <td className="px-4 py-3">
                                            <DiffViewer properties={log.properties} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{logs.total} entries</span>
                        <div className="flex gap-2">
                            {logs.current_page > 1 && (
                                <button
                                    onClick={() => applyFilters({ page: logs.current_page - 1 })}
                                    className="px-3 py-1.5 rounded-lg border border-border/80 hover:bg-muted/50 transition-colors text-foreground"
                                >
                                    Previous
                                </button>
                            )}
                            {logs.current_page < logs.last_page && (
                                <button
                                    onClick={() => applyFilters({ page: logs.current_page + 1 })}
                                    className="px-3 py-1.5 rounded-lg border border-border/80 hover:bg-muted/50 transition-colors text-foreground"
                                >
                                    Next
                                </button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
