import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { GlobeIcon } from 'lucide-react';

interface Props {
    portal: {
        enabled: boolean;
        name: string;
        welcome_text: string;
        url: string;
    };
}

export default function Portal({ portal }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        enabled: portal.enabled,
        name: portal.name,
        welcome_text: portal.welcome_text,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('settings.portal.update'));
    }

    return (
        <AppLayout>
            <Head title="Customer Portal Settings" />

            <div className="w-full px-6 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Customer Portal</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Let customers submit and track support tickets without agent access.
                    </p>
                </div>

                <div className="rounded-xl border border-border bg-card max-w-2xl">
                    <div className="flex items-start gap-3 px-6 py-5 border-b border-border">
                        <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <GlobeIcon className="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <h2 className="text-[15px] font-semibold">Portal settings</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">Configure the self-service portal for your customers.</p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="px-6 py-5 space-y-5">
                        {/* Enable toggle */}
                        <div className="flex items-center justify-between rounded-lg border border-border px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium">Enable portal</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Makes the portal publicly accessible at your portal URL.
                                </p>
                            </div>
                            <Switch checked={data.enabled} onCheckedChange={(v) => setData('enabled', v)} />
                        </div>

                        {/* Portal name */}
                        <div className="space-y-1.5">
                            <Label htmlFor="portal-name">Portal name</Label>
                            <Input
                                id="portal-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Acme Support"
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            <p className="text-xs text-muted-foreground">Shown as the portal title to customers.</p>
                        </div>

                        {/* Welcome message */}
                        <div className="space-y-1.5">
                            <Label htmlFor="portal-welcome">Welcome message</Label>
                            <textarea
                                id="portal-welcome"
                                value={data.welcome_text}
                                onChange={(e) => setData('welcome_text', e.target.value)}
                                rows={3}
                                placeholder="Submit and track your support requests."
                                className={`w-full rounded-md border px-3 py-2 text-sm bg-transparent focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none ${errors.welcome_text ? 'border-destructive' : 'border-input'}`}
                            />
                            {errors.welcome_text && <p className="text-xs text-destructive">{errors.welcome_text}</p>}
                        </div>

                        {/* Portal URL */}
                        <div className="rounded-lg bg-muted/50 border border-border px-4 py-3.5 space-y-1.5">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Portal URL</p>
                            <div className="flex items-center gap-3">
                                <code className="flex-1 text-sm font-mono text-foreground break-all">{portal.url}</code>
                                <button
                                    type="button"
                                    onClick={() => navigator.clipboard.writeText(portal.url)}
                                    className="shrink-0 text-xs text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                            <p className="text-xs text-muted-foreground">Share this URL with your customers.</p>
                        </div>

                        <div className="flex justify-end pt-1">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save settings'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
