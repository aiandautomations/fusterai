import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { UsersIcon, MailboxIcon, ShuffleIcon } from 'lucide-react';

interface MailboxConfig {
    mailbox_id: number;
    mailbox_name: string;
    mailbox_email: string;
    mode: 'round_robin' | 'least_loaded';
    active: boolean;
    agent_count: number;
}

interface Props {
    configs: MailboxConfig[];
}

const MODE_DESCRIPTIONS: Record<string, string> = {
    round_robin: 'Assign to each agent in turn, evenly distributing load.',
    least_loaded: 'Assign to the agent with the fewest open conversations.',
};

export default function RoutingSettings({ configs }: Props) {
    const { data, setData, post, processing, errors } = useForm({ configs });

    function updateConfig(index: number, field: keyof MailboxConfig, value: unknown) {
        const updated = [...data.configs];
        updated[index] = { ...updated[index], [field]: value };
        setData('configs', updated);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/routing');
    }

    return (
        <AppLayout>
            <Head title="Conversation Routing" />
            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Conversation Routing</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Automatically assign new conversations to agents. Only unassigned conversations are affected.
                    </p>
                </div>

                {data.configs.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-muted/20 px-6 py-12 text-center">
                        <MailboxIcon className="mx-auto h-8 w-8 text-muted-foreground mb-3" />
                        <p className="text-sm text-muted-foreground">No active mailboxes found.</p>
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="rounded-xl border border-border bg-card overflow-hidden">
                            {/* Header */}
                            <div className="grid grid-cols-[1fr_180px_120px_80px] gap-4 px-6 py-3 bg-muted/40 border-b border-border text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                                <span>Mailbox</span>
                                <span>Strategy</span>
                                <span className="flex items-center gap-1.5">
                                    <UsersIcon className="h-3 w-3" /> Agents
                                </span>
                                <span>Active</span>
                            </div>

                            {data.configs.map((config, i) => (
                                <div
                                    key={config.mailbox_id}
                                    className="grid grid-cols-[1fr_180px_120px_80px] gap-4 items-center px-6 py-4 border-b border-border last:border-0"
                                >
                                    {/* Mailbox info */}
                                    <div>
                                        <p className="text-sm font-medium">{config.mailbox_name}</p>
                                        <p className="text-xs text-muted-foreground">{config.mailbox_email}</p>
                                    </div>

                                    {/* Mode selector */}
                                    <div>
                                        <Select
                                            value={config.mode}
                                            onValueChange={(v) => updateConfig(i, 'mode', v)}
                                            disabled={!config.active}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="round_robin">
                                                    <div className="flex items-center gap-2">
                                                        <ShuffleIcon className="h-3.5 w-3.5" />
                                                        Round Robin
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="least_loaded">
                                                    <div className="flex items-center gap-2">
                                                        <UsersIcon className="h-3.5 w-3.5" />
                                                        Least Loaded
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {config.active && (
                                            <p className="text-xs text-muted-foreground mt-1">{MODE_DESCRIPTIONS[config.mode]}</p>
                                        )}
                                    </div>

                                    {/* Agent count */}
                                    <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <UsersIcon className="h-3.5 w-3.5" />
                                        {config.agent_count} agent{config.agent_count !== 1 ? 's' : ''}
                                    </div>

                                    {/* Active toggle */}
                                    <Switch
                                        checked={config.active}
                                        onCheckedChange={(v) => updateConfig(i, 'active', v)}
                                        disabled={config.agent_count === 0}
                                        title={config.agent_count === 0 ? 'Assign agents to this mailbox first' : undefined}
                                    />
                                </div>
                            ))}
                        </div>

                        {typeof errors.configs === 'string' && <p className="text-xs text-destructive">{errors.configs}</p>}

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save Routing Settings'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
