import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Switch } from '@/Components/ui/switch';
import { ClockIcon } from 'lucide-react';

interface Policy {
    priority: 'urgent' | 'high' | 'normal' | 'low';
    first_response_minutes: number;
    resolution_minutes: number;
    active: boolean;
}

interface Props {
    policies: Policy[];
}

const PRIORITY_LABELS: Record<string, string> = {
    urgent: 'Urgent',
    high: 'High',
    normal: 'Normal',
    low: 'Low',
};

const PRIORITY_COLORS: Record<string, string> = {
    urgent: 'bg-destructive/10 text-destructive',
    high: 'bg-orange-500/10 text-orange-600',
    normal: 'bg-primary/10 text-primary',
    low: 'bg-muted text-muted-foreground',
};

function minutesToHoursMinutes(minutes: number): { hours: number; mins: number } {
    return { hours: Math.floor(minutes / 60), mins: minutes % 60 };
}

function hoursMinutesToMinutes(hours: number, mins: number): number {
    return hours * 60 + mins;
}

export default function SlaSettings({ policies }: Props) {
    const { data, setData, post, processing, errors } = useForm({ policies });

    function updatePolicy(index: number, field: keyof Policy, value: unknown) {
        const updated = [...data.policies];
        updated[index] = { ...updated[index], [field]: value };
        setData('policies', updated);
    }

    function updateTime(index: number, field: 'first_response_minutes' | 'resolution_minutes', part: 'hours' | 'mins', value: string) {
        const current = minutesToHoursMinutes(data.policies[index][field]);
        const num = Math.max(0, parseInt(value) || 0);
        const newMinutes = part === 'hours' ? hoursMinutesToMinutes(num, current.mins) : hoursMinutesToMinutes(current.hours, num);
        updatePolicy(index, field, Math.max(1, newMinutes));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/sla');
    }

    return (
        <AppLayout>
            <Head title="SLA Settings" />
            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">SLA Policies</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Configure first response and resolution time targets per priority level.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="rounded-xl border border-border bg-card overflow-hidden">
                        {/* Header */}
                        <div className="grid grid-cols-[160px_1fr_1fr_80px] gap-4 px-6 py-3 bg-muted/40 border-b border-border text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <span>Priority</span>
                            <span className="flex items-center gap-1.5">
                                <ClockIcon className="h-3 w-3" />
                                First Response
                            </span>
                            <span className="flex items-center gap-1.5">
                                <ClockIcon className="h-3 w-3" />
                                Resolution
                            </span>
                            <span>Active</span>
                        </div>

                        {data.policies.map((policy, i) => {
                            const fr = minutesToHoursMinutes(policy.first_response_minutes);
                            const res = minutesToHoursMinutes(policy.resolution_minutes);
                            return (
                                <div
                                    key={policy.priority}
                                    className="grid grid-cols-[160px_1fr_1fr_80px] gap-4 items-center px-6 py-4 border-b border-border last:border-0"
                                >
                                    <span
                                        className={`inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${PRIORITY_COLORS[policy.priority]}`}
                                    >
                                        {PRIORITY_LABELS[policy.priority]}
                                    </span>

                                    {/* First response */}
                                    <div className="flex items-center gap-1.5">
                                        <Input
                                            type="number"
                                            min="0"
                                            value={fr.hours}
                                            onChange={(e) => updateTime(i, 'first_response_minutes', 'hours', e.target.value)}
                                            className="w-20 text-center"
                                        />
                                        <span className="text-xs text-muted-foreground">h</span>
                                        <Input
                                            type="number"
                                            min="0"
                                            max="59"
                                            value={fr.mins}
                                            onChange={(e) => updateTime(i, 'first_response_minutes', 'mins', e.target.value)}
                                            className="w-20 text-center"
                                        />
                                        <span className="text-xs text-muted-foreground">m</span>
                                    </div>

                                    {/* Resolution */}
                                    <div className="flex items-center gap-1.5">
                                        <Input
                                            type="number"
                                            min="0"
                                            value={res.hours}
                                            onChange={(e) => updateTime(i, 'resolution_minutes', 'hours', e.target.value)}
                                            className="w-20 text-center"
                                        />
                                        <span className="text-xs text-muted-foreground">h</span>
                                        <Input
                                            type="number"
                                            min="0"
                                            max="59"
                                            value={res.mins}
                                            onChange={(e) => updateTime(i, 'resolution_minutes', 'mins', e.target.value)}
                                            className="w-20 text-center"
                                        />
                                        <span className="text-xs text-muted-foreground">m</span>
                                    </div>

                                    <Switch checked={policy.active} onCheckedChange={(v) => updatePolicy(i, 'active', v)} />
                                </div>
                            );
                        })}
                    </div>

                    {errors.policies && <p className="text-xs text-destructive">{errors.policies}</p>}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save SLA Policies'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
