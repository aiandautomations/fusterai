import { router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { cn } from '@/lib/utils';

interface SurveyStats {
    total: number;
    good: number;
    bad: number;
    score: number | null;
}

interface RecentResponse {
    id: number;
    rating: 'good' | 'bad';
    responded_at: string;
    conversation_id: number;
    subject: string;
}

interface Props {
    stats: SurveyStats;
    recent: RecentResponse[];
    days: number;
}

function StatCard({ label, value, tone }: { label: string; value: string | number; tone?: 'success' | 'destructive' | 'primary' }) {
    const cls =
        tone === 'success'
            ? 'text-success'
            : tone === 'destructive'
              ? 'text-destructive'
              : tone === 'primary'
                ? 'text-primary'
                : 'text-foreground';
    return (
        <div className="rounded-xl border border-border/80 bg-card/75 p-5">
            <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">{label}</p>
            <p className={cn('text-3xl font-bold mt-1', cls)}>{value}</p>
        </div>
    );
}

export default function SurveyReport({ stats, recent, days }: Props) {
    return (
        <AppLayout title="CSAT Report">
            <div className="w-full px-6 py-8 space-y-10">
                <div className="flex items-end justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">CSAT Report</h1>
                        <p className="text-sm text-muted-foreground mt-1">Customer satisfaction survey results.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href={route('settings.survey')} className="text-sm text-primary hover:underline">
                            ← Settings
                        </Link>
                        <Select value={String(days)} onValueChange={(v) => router.get(route('settings.survey.report'), { days: v })}>
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

                <section className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <StatCard label="Total Responses" value={stats.total} />
                    <StatCard label="Positive 👍" value={stats.good} tone="success" />
                    <StatCard label="Negative 👎" value={stats.bad} tone="destructive" />
                    <StatCard
                        label="CSAT Score"
                        value={stats.score !== null ? `${stats.score}%` : '—'}
                        tone="primary"
                    />
                </section>

                {stats.total > 0 && (
                    <section className="space-y-2">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>👍 {stats.good}</span>
                            <span>{stats.bad} 👎</span>
                        </div>
                        <div className="h-3 rounded-full bg-muted overflow-hidden">
                            <div
                                className="h-full rounded-full bg-success transition-all"
                                style={{ width: `${stats.score ?? 0}%` }}
                            />
                        </div>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Recent Responses</h2>
                    <div className="rounded-xl border border-border/80 bg-card/75 overflow-hidden">
                        {recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground px-5 py-8 text-center">No survey responses in this period.</p>
                        ) : (
                            <ul className="divide-y">
                                {recent.map((r) => (
                                    <li key={r.id} className="flex items-center gap-4 px-5 py-3">
                                        <span className="text-xl shrink-0">{r.rating === 'good' ? '👍' : '👎'}</span>
                                        <div className="flex-1 min-w-0">
                                            <Link
                                                href={`/conversations/${r.conversation_id}`}
                                                className="text-sm font-medium hover:underline truncate block"
                                            >
                                                {r.subject || '(No subject)'}
                                            </Link>
                                        </div>
                                        <span className="text-xs text-muted-foreground shrink-0">
                                            {new Date(r.responded_at).toLocaleDateString()}
                                        </span>
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
