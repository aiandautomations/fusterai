import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ClockIcon, AlertTriangleIcon, CheckCircleIcon, UserIcon, MailboxIcon } from 'lucide-react';

interface MailboxRow {
    mailbox_id: number;
    mailbox_name: string;
    total: number;
    fr_breached: number;
    res_breached: number;
    fr_breach_rate: number;
    res_breach_rate: number;
}

interface AgentRow {
    user_id: number;
    agent_name: string;
    total: number;
    fr_breached: number;
    res_breached: number;
    fr_breach_rate: number;
    res_breach_rate: number;
    avg_fr_minutes: number;
}

interface Props {
    days: number;
    total: number;
    fr_breached: number;
    res_breached: number;
    fr_achieved: number;
    res_achieved: number;
    fr_breach_rate: number;
    res_breach_rate: number;
    avg_first_response_min: number;
    avg_resolution_min: number;
    by_mailbox: MailboxRow[];
    by_agent: AgentRow[];
}

function formatMinutes(minutes: number): string {
    if (!minutes) return '—';
    if (minutes < 60) return `${minutes}m`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

function Breach({ rate }: { rate: number }) {
    const color =
        rate === 0
            ? 'text-green-600 dark:text-green-400'
            : rate < 20
              ? 'text-yellow-600 dark:text-yellow-400'
              : 'text-red-600 dark:text-red-400';
    return <span className={`font-semibold ${color}`}>{rate}%</span>;
}

export default function SlaReport({
    days,
    total,
    fr_achieved,
    res_achieved,
    fr_breach_rate,
    res_breach_rate,
    avg_first_response_min,
    avg_resolution_min,
    by_mailbox,
    by_agent,
}: Props) {
    const RANGES = [7, 30, 90];

    return (
        <AppLayout>
            <Head title="SLA Report" />
            <div className="w-full px-6 py-8 space-y-8">
                {/* Header + date range */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">SLA Report</h1>
                        <p className="text-sm text-muted-foreground mt-1">Service level agreement performance over the last {days} days.</p>
                    </div>
                    <div className="flex gap-1 rounded-lg border border-border p-1 bg-muted/30">
                        {RANGES.map((d) => (
                            <button
                                key={d}
                                onClick={() => router.get('/settings/sla/report', { days: d }, { preserveState: false })}
                                className={`px-3 py-1 rounded-md text-sm font-medium transition-colors ${
                                    days === d ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {d}d
                            </button>
                        ))}
                    </div>
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard icon={<ClockIcon className="h-4 w-4" />} label="Total Conversations" value={total} />
                    <StatCard
                        icon={<CheckCircleIcon className="h-4 w-4 text-green-500" />}
                        label="Avg First Response"
                        value={formatMinutes(avg_first_response_min)}
                        sub={`${fr_achieved} achieved`}
                    />
                    <StatCard
                        icon={<CheckCircleIcon className="h-4 w-4 text-green-500" />}
                        label="Avg Resolution"
                        value={formatMinutes(avg_resolution_min)}
                        sub={`${res_achieved} resolved`}
                    />
                    <StatCard
                        icon={<AlertTriangleIcon className="h-4 w-4 text-red-500" />}
                        label="Breach Rates"
                        value={
                            <span>
                                <Breach rate={fr_breach_rate} /> / <Breach rate={res_breach_rate} />
                            </span>
                        }
                        sub="First Response / Resolution"
                    />
                </div>

                {total === 0 && (
                    <div className="rounded-xl border border-dashed border-border px-6 py-12 text-center text-sm text-muted-foreground">
                        No SLA data for this period.
                    </div>
                )}

                {/* By Mailbox */}
                {by_mailbox.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                            <MailboxIcon className="h-4 w-4" /> By Mailbox
                        </h2>
                        <div className="rounded-xl border border-border bg-card overflow-hidden">
                            <Table
                                headers={['Mailbox', 'Total', 'FR Breached', 'Res Breached', 'FR Breach Rate', 'Res Breach Rate']}
                                rows={by_mailbox.map((r) => [
                                    r.mailbox_name,
                                    r.total,
                                    r.fr_breached,
                                    r.res_breached,
                                    <Breach rate={r.fr_breach_rate} />,
                                    <Breach rate={r.res_breach_rate} />,
                                ])}
                            />
                        </div>
                    </section>
                )}

                {/* By Agent */}
                {by_agent.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                            <UserIcon className="h-4 w-4" /> By Agent
                        </h2>
                        <div className="rounded-xl border border-border bg-card overflow-hidden">
                            <Table
                                headers={[
                                    'Agent',
                                    'Total',
                                    'FR Breached',
                                    'Res Breached',
                                    'FR Breach Rate',
                                    'Res Breach Rate',
                                    'Avg FR Time',
                                ]}
                                rows={by_agent.map((r) => [
                                    r.agent_name,
                                    r.total,
                                    r.fr_breached,
                                    r.res_breached,
                                    <Breach rate={r.fr_breach_rate} />,
                                    <Breach rate={r.res_breach_rate} />,
                                    formatMinutes(r.avg_fr_minutes),
                                ])}
                            />
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function StatCard({ icon, label, value, sub }: { icon: React.ReactNode; label: string; value: React.ReactNode; sub?: string }) {
    return (
        <div className="rounded-xl border border-border bg-card p-4 space-y-1">
            <div className="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                {icon}
                {label}
            </div>
            <div className="text-2xl font-bold tracking-tight">{value}</div>
            {sub && <div className="text-xs text-muted-foreground">{sub}</div>}
        </div>
    );
}

function Table({ headers, rows }: { headers: string[]; rows: React.ReactNode[][] }) {
    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="border-b border-border bg-muted/40">
                    {headers.map((h) => (
                        <th
                            key={h}
                            className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide first:pl-6 last:pr-6"
                        >
                            {h}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row, i) => (
                    <tr key={i} className="border-b border-border last:border-0 hover:bg-muted/20 transition-colors">
                        {row.map((cell, j) => (
                            <td key={j} className="px-4 py-3 first:pl-6 last:pr-6">
                                {cell}
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
