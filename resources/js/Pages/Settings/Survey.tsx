import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Input } from '@/Components/ui/input';
import { ThumbsUpIcon } from 'lucide-react';

interface Props {
    survey: {
        enabled: boolean;
        delay_minutes: number;
    };
}

export default function Survey({ survey }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        enabled: survey.enabled,
        delay_minutes: survey.delay_minutes,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('settings.survey.update'));
    }

    return (
        <AppLayout>
            <Head title="CSAT Survey Settings" />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex items-end justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Satisfaction Survey</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Automatically email customers a rating survey when a conversation is closed.
                        </p>
                    </div>
                    <Link href={route('settings.survey.report')} className="text-sm text-primary hover:underline">
                        View report →
                    </Link>
                </div>

                <div className="rounded-xl border border-border bg-card max-w-2xl">
                    <div className="flex items-start gap-3 px-6 py-5 border-b border-border">
                        <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <ThumbsUpIcon className="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <h2 className="text-[15px] font-semibold">Survey settings</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                A 👍 / 👎 email is sent to the customer after a conversation closes.
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="px-6 py-5 space-y-5">
                        <div className="flex items-center justify-between rounded-lg border border-border px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium">Enable surveys</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Send satisfaction surveys when conversations are closed.
                                </p>
                            </div>
                            <Switch checked={data.enabled} onCheckedChange={(checked) => setData('enabled', checked)} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="delay_minutes">Send delay (minutes)</Label>
                            <Input
                                id="delay_minutes"
                                type="number"
                                min={0}
                                max={1440}
                                value={data.delay_minutes}
                                onChange={(e) => setData('delay_minutes', Number(e.target.value))}
                                className="max-w-xs"
                            />
                            {errors.delay_minutes && <p className="text-xs text-destructive">{errors.delay_minutes}</p>}
                            <p className="text-xs text-muted-foreground">
                                How long to wait after closing before sending the survey (0 = immediately).
                            </p>
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button type="submit" disabled={processing}>
                                Save settings
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
