import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { Card, CardContent } from '@/Components/ui/card';
import { PencilIcon, Trash2Icon } from 'lucide-react';

type Condition = { field: string; operator: string; value: string };
type Action = { type: string; value: string };
type Rule = {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    trigger: string;
    conditions: Condition[];
    actions: Action[];
    run_count: number;
    last_run_at: string | null;
};
type Trigger = { value: string; label: string };

interface Props {
    rules: Rule[];
    triggers: Trigger[];
}

export default function AutomationIndex({ rules, triggers }: Props) {
    const triggerLabel = (v: string) => triggers.find((t) => t.value === v)?.label ?? v;

    function toggle(rule: Rule) {
        router.patch(`/automation/${rule.id}/toggle`);
    }

    function destroy(rule: Rule) {
        if (!confirm(`Delete rule "${rule.name}"?`)) return;
        router.delete(`/automation/${rule.id}`);
    }

    return (
        <AppLayout title="Automation">
            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">Automation Rules</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Automatically act on conversations based on triggers and conditions.
                        </p>
                    </div>
                    <Button onClick={() => router.get('/automation/create')}>+ New Rule</Button>
                </div>

                {rules.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="p-12 text-center text-muted-foreground">
                            <p className="text-lg font-medium">No automation rules yet</p>
                            <p className="text-sm mt-1">Create your first rule to automate repetitive tasks.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {rules.map((rule) => (
                            <Card key={rule.id} className="border-border/80 bg-card/75">
                                <CardContent className="flex items-start justify-between gap-4 p-4">
                                    <div className="flex items-start gap-3 flex-1 min-w-0">
                                        {/* Toggle */}
                                        <Switch
                                            checked={rule.active}
                                            onCheckedChange={() => toggle(rule)}
                                            className="mt-0.5"
                                            title={rule.active ? 'Active — click to disable' : 'Inactive — click to enable'}
                                        />

                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium truncate">{rule.name}</p>
                                            {rule.description && <p className="text-sm text-muted-foreground">{rule.description}</p>}
                                            <div className="flex items-center gap-2 mt-1 flex-wrap">
                                                <Badge variant="secondary">{triggerLabel(rule.trigger)}</Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {rule.conditions.length} condition{rule.conditions.length !== 1 ? 's' : ''}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {rule.actions.length} action{rule.actions.length !== 1 ? 's' : ''}
                                                </span>
                                                {rule.run_count > 0 && (
                                                    <span className="text-xs text-muted-foreground">Ran {rule.run_count}×</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        <button
                                            onClick={() => router.get(`/automation/${rule.id}/edit`)}
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Edit rule"
                                            aria-label="Edit rule"
                                        >
                                            <PencilIcon className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={() => destroy(rule)}
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-destructive transition-colors hover:bg-destructive/10"
                                            title="Delete rule"
                                            aria-label="Delete rule"
                                        >
                                            <Trash2Icon className="h-4 w-4" />
                                        </button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
