import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/Components/ui/chart';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid } from 'recharts';
import { cn } from '@/lib/utils';

interface MailboxStat {
    mailbox_id: number;
    count: number;
    mailbox?: { id: number; name: string };
}
interface ChannelStat {
    channel_type: string;
    count: number;
}
interface PriorityStat {
    priority: string;
    count: number;
}
interface TrendStat {
    date: string;
    count: number;
}
interface AgentStat {
    id: number;
    name: string;
    email: string;
    resolved_count: number;
}

interface Stats {
    total: number;
    open: number;
    pending: number;
    closed: number;
    trend: TrendStat[];
    by_channel: ChannelStat[];
    by_mailbox: MailboxStat[];
    by_priority: PriorityStat[];
    top_agents: AgentStat[];
    avg_resolution_hours: number;
}

interface Props {
    stats: Stats;
    days: number;
}

const trendChartConfig = {
    count: { label: 'Conversations', color: 'var(--primary)' },
} satisfies ChartConfig;

function StatCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number | string;
    tone?: 'default' | 'info' | 'warning' | 'success' | 'primary';
}) {
    const toneClass =
        tone === 'info'
            ? 'text-info'
            : tone === 'warning'
              ? 'text-warning'
              : tone === 'success'
                ? 'text-success'
                : tone === 'primary'
                  ? 'text-primary'
                  : 'text-foreground';

    return (
        <div className="rounded-xl border border-border/80 bg-card/75 p-5">
            <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">{label}</p>
            <p className={cn('text-3xl font-bold mt-1', toneClass)}>{value}</p>
        </div>
    );
}

function formatDate(dateStr: string) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export default function ReportsIndex({ stats, days }: Props) {
    const trendData = stats.trend.map((t) => ({ ...t, date: formatDate(t.date) }));

    return (
        <AppLayout title="Reports">
            <div className="w-full px-6 py-8 space-y-10">
                <div className="flex items-end justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Reports</h1>
                        <p className="text-sm text-muted-foreground mt-1">Overview of your workspace activity.</p>
                    </div>
                    <Select value={String(days)} onValueChange={(value) => router.get('/reports', { days: value })}>
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="7">Last 7 days</SelectItem>
                            <SelectItem value="14">Last 14 days</SelectItem>
                            <SelectItem value="30">Last 30 days</SelectItem>
                            <SelectItem value="90">Last 90 days</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Summary */}
                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Overview</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <StatCard label="Total" value={stats.total} />
                        <StatCard label="Open" value={stats.open} tone="info" />
                        <StatCard label="Pending" value={stats.pending} tone="warning" />
                        <StatCard label="Closed" value={stats.closed} tone="success" />
                        <StatCard
                            label="Avg Resolution"
                            value={stats.avg_resolution_hours > 0 ? `${stats.avg_resolution_hours.toFixed(1)}h` : '—'}
                            tone="primary"
                        />
                    </div>
                </section>

                {/* Trend chart */}
                {trendData.length > 1 && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Conversation Trend</h2>
                            <span className="text-xs text-muted-foreground">{stats.total} total in period</span>
                        </div>
                        <div className="rounded-xl border border-border/80 bg-card/75 p-5">
                            <ChartContainer config={trendChartConfig} className="h-52 w-full">
                                <AreaChart data={trendData} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="trend-gradient" x1="0" y1="0" x2="0" y2="1">
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
                                        fill="url(#trend-gradient)"
                                        dot={false}
                                        activeDot={{ r: 4, fill: 'var(--color-count)' }}
                                    />
                                </AreaChart>
                            </ChartContainer>
                        </div>
                    </section>
                )}

                {/* By Channel */}
                {stats.by_channel.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">By Channel</h2>
                        <div className="bg-card/75 border border-border/80 rounded-xl overflow-hidden">
                            <ul className="divide-y">
                                {(() => {
                                    const max = Math.max(...stats.by_channel.map((i) => i.count), 1);
                                    return stats.by_channel.map((item) => (
                                        <li key={item.channel_type} className="flex items-center gap-4 px-5 py-3">
                                            <span className="text-sm font-medium capitalize w-24 shrink-0">{item.channel_type}</span>
                                            <div className="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                                                <div
                                                    className="h-full rounded-full bg-primary/50 transition-all"
                                                    style={{ width: `${(item.count / max) * 100}%` }}
                                                />
                                            </div>
                                            <span className="text-sm tabular-nums text-muted-foreground w-8 text-right">{item.count}</span>
                                        </li>
                                    ));
                                })()}
                            </ul>
                        </div>
                    </section>
                )}

                {/* By Mailbox */}
                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">By Mailbox</h2>
                    <div className="bg-card/75 border border-border/80 rounded-xl overflow-hidden">
                        {stats.by_mailbox.length === 0 ? (
                            <p className="text-sm text-muted-foreground px-5 py-8 text-center">No mailbox data.</p>
                        ) : (
                            <ul className="divide-y">
                                {(() => {
                                    const max = Math.max(...stats.by_mailbox.map((i) => i.count), 1);
                                    return stats.by_mailbox.map((item) => (
                                        <li key={item.mailbox_id} className="flex items-center gap-4 px-5 py-3">
                                            <span className="text-sm font-medium flex-1 min-w-0 truncate">
                                                {item.mailbox?.name ?? `Mailbox #${item.mailbox_id}`}
                                            </span>
                                            <div className="w-32 h-1.5 rounded-full bg-muted overflow-hidden shrink-0">
                                                <div
                                                    className="h-full rounded-full bg-primary/50 transition-all"
                                                    style={{ width: `${(item.count / max) * 100}%` }}
                                                />
                                            </div>
                                            <span className="text-sm tabular-nums text-muted-foreground w-24 text-right shrink-0">
                                                {item.count} conversations
                                            </span>
                                        </li>
                                    ));
                                })()}
                            </ul>
                        )}
                    </div>
                </section>

                {/* By Priority */}
                {stats.by_priority.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">By Priority</h2>
                        <div className="bg-card/75 border border-border/80 rounded-xl overflow-hidden">
                            <ul className="divide-y">
                                {(() => {
                                    const max = Math.max(...stats.by_priority.map((i) => i.count), 1);
                                    return stats.by_priority.map((item) => (
                                        <li key={item.priority} className="flex items-center gap-4 px-5 py-3">
                                            <span className="text-sm font-medium capitalize w-24 shrink-0">{item.priority}</span>
                                            <div className="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                                                <div
                                                    className="h-full rounded-full bg-primary/50 transition-all"
                                                    style={{ width: `${(item.count / max) * 100}%` }}
                                                />
                                            </div>
                                            <span className="text-sm tabular-nums text-muted-foreground w-8 text-right">{item.count}</span>
                                        </li>
                                    ));
                                })()}
                            </ul>
                        </div>
                    </section>
                )}

                {/* Top Agents */}
                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Top Agents</h2>
                    <div className="bg-card/75 border border-border/80 rounded-xl overflow-hidden">
                        {stats.top_agents.length === 0 ? (
                            <p className="text-sm text-muted-foreground px-5 py-8 text-center">No agent data.</p>
                        ) : (
                            <ul className="divide-y">
                                {stats.top_agents.map((agent, idx) => (
                                    <li key={agent.id} className="flex items-center gap-4 px-5 py-3">
                                        <span className="text-sm font-bold text-muted-foreground w-5">{idx + 1}</span>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate">{agent.name}</p>
                                            <p className="text-xs text-muted-foreground truncate">{agent.email}</p>
                                        </div>
                                        <span className="text-sm font-semibold text-success">{agent.resolved_count} resolved</span>
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
