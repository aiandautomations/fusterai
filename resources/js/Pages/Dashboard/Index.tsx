import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/Components/ui/chart';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid } from 'recharts';
import { cn } from '@/lib/utils';
import { InboxIcon, ClockIcon, UserIcon, AlertCircleIcon } from 'lucide-react';

interface TrendStat {
    date: string;
    count: number;
}
interface AgentStat {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    resolved_count: number;
}
interface RecentConv {
    id: number;
    subject: string;
    status: string;
    priority: string;
    last_reply_at: string | null;
    customer: { id: number; name: string; email: string } | null;
}

interface Stats {
    open: number;
    pending: number;
    mine: number;
    unassigned: number;
    trend: TrendStat[];
}

interface Props {
    stats: Stats;
    topAgents: AgentStat[];
    recent: RecentConv[];
}

const trendConfig = { count: { label: 'Conversations', color: 'var(--primary)' } } satisfies ChartConfig;

function StatCard({
    label,
    value,
    tone,
    icon,
}: {
    label: string;
    value: number;
    tone?: 'info' | 'warning' | 'primary' | 'default';
    icon: React.ReactNode;
}) {
    const valueClass =
        tone === 'info' ? 'text-info' : tone === 'warning' ? 'text-warning' : tone === 'primary' ? 'text-primary' : 'text-foreground';
    return (
        <div className="rounded-xl border border-border/80 bg-card/75 p-5 flex items-start gap-4">
            <div className="mt-0.5 rounded-lg bg-muted/60 p-2 text-muted-foreground shrink-0">{icon}</div>
            <div>
                <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">{label}</p>
                <p className={cn('text-3xl font-bold mt-1', valueClass)}>{value}</p>
            </div>
        </div>
    );
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function timeAgo(d: string | null) {
    if (!d) return '—';
    const mins = Math.floor((Date.now() - new Date(d).getTime()) / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

const statusColors: Record<string, string> = {
    open: 'bg-info/15 text-info',
    pending: 'bg-warning/15 text-warning',
    closed: 'bg-muted text-muted-foreground',
    spam: 'bg-destructive/10 text-destructive',
};

export default function DashboardIndex({ stats, topAgents, recent }: Props) {
    const trendData = stats.trend.map((t) => ({ ...t, date: formatDate(t.date) }));

    return (
        <AppLayout title="Dashboard">
            <div className="w-full px-6 py-8 space-y-10">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-sm text-muted-foreground mt-1">Your workspace at a glance.</p>
                </div>

                {/* Stat cards */}
                <section className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <StatCard label="Open" value={stats.open} tone="info" icon={<InboxIcon className="h-4 w-4" />} />
                    <StatCard label="Pending" value={stats.pending} tone="warning" icon={<ClockIcon className="h-4 w-4" />} />
                    <StatCard label="Mine" value={stats.mine} tone="primary" icon={<UserIcon className="h-4 w-4" />} />
                    <StatCard label="Unassigned" value={stats.unassigned} icon={<AlertCircleIcon className="h-4 w-4" />} />
                </section>

                {/* Trend + top agents */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <section className="lg:col-span-2 space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">
                            New Conversations — Last 14 Days
                        </h2>
                        <div className="rounded-xl border border-border/80 bg-card/75 p-5">
                            {trendData.length > 1 ? (
                                <ChartContainer config={trendConfig} className="h-48 w-full">
                                    <AreaChart data={trendData} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                                        <defs>
                                            <linearGradient id="dash-grad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="var(--color-count)" stopOpacity={0.3} />
                                                <stop offset="95%" stopColor="var(--color-count)" stopOpacity={0.02} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
                                        <XAxis
                                            dataKey="date"
                                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                                            tickLine={false}
                                            axisLine={false}
                                            interval="preserveStartEnd"
                                        />
                                        <YAxis
                                            allowDecimals={false}
                                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                                            tickLine={false}
                                            axisLine={false}
                                        />
                                        <ChartTooltip content={<ChartTooltipContent />} />
                                        <Area
                                            type="monotone"
                                            dataKey="count"
                                            stroke="var(--color-count)"
                                            strokeWidth={2}
                                            fill="url(#dash-grad)"
                                            dot={false}
                                            activeDot={{ r: 4, fill: 'var(--color-count)' }}
                                        />
                                    </AreaChart>
                                </ChartContainer>
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-16">Not enough data yet.</p>
                            )}
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Top Agents This Month</h2>
                        <div className="rounded-xl border border-border/80 bg-card/75 overflow-hidden">
                            {topAgents.length === 0 ? (
                                <p className="text-sm text-muted-foreground px-5 py-8 text-center">No data yet.</p>
                            ) : (
                                <ul className="divide-y">
                                    {topAgents.map((agent, idx) => (
                                        <li key={agent.id} className="flex items-center gap-3 px-4 py-3">
                                            <span className="text-sm font-bold text-muted-foreground w-5 shrink-0">{idx + 1}</span>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium truncate">{agent.name}</p>
                                                <p className="text-xs text-muted-foreground truncate">{agent.email}</p>
                                            </div>
                                            <span className="text-sm font-semibold text-success shrink-0">{agent.resolved_count}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                {/* Recent conversations */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Recent Conversations</h2>
                        <Link href="/conversations" className="text-xs text-primary hover:underline">
                            View all →
                        </Link>
                    </div>
                    <div className="rounded-xl border border-border/80 bg-card/75 overflow-hidden">
                        {recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground px-5 py-8 text-center">No conversations yet.</p>
                        ) : (
                            <ul className="divide-y">
                                {recent.map((conv) => (
                                    <li key={conv.id}>
                                        <Link
                                            href={`/conversations/${conv.id}`}
                                            className="flex items-center gap-4 px-5 py-3 hover:bg-muted/40 transition-colors"
                                        >
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium truncate">{conv.subject || '(No subject)'}</p>
                                                <p className="text-xs text-muted-foreground truncate">
                                                    {conv.customer?.name ?? conv.customer?.email ?? 'Unknown'}
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    'text-[11px] font-medium px-2 py-0.5 rounded-full capitalize shrink-0',
                                                    statusColors[conv.status] ?? 'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {conv.status}
                                            </span>
                                            <span className="text-xs text-muted-foreground shrink-0 w-16 text-right">
                                                {timeAgo(conv.last_reply_at)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
