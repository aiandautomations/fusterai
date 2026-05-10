import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import type { Mailbox } from '@/types';
import {
    SaveIcon,
    EyeIcon,
    EyeOffIcon,
    ArrowLeftIcon,
    MailIcon,
    ServerIcon,
    SendIcon,
    BotIcon,
    ShieldCheckIcon,
    MessageCircleIcon,
    ArrowRightIcon,
    ClockIcon,
} from 'lucide-react';

const TIMEZONES = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Sao_Paulo',
    'America/Toronto',
    'America/Vancouver',
    'America/Mexico_City',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Amsterdam',
    'Europe/Madrid',
    'Europe/Rome',
    'Europe/Stockholm',
    'Europe/Warsaw',
    'Europe/Istanbul',
    'Europe/Moscow',
    'Asia/Dubai',
    'Asia/Kolkata',
    'Asia/Colombo',
    'Asia/Dhaka',
    'Asia/Bangkok',
    'Asia/Singapore',
    'Asia/Hong_Kong',
    'Asia/Shanghai',
    'Asia/Tokyo',
    'Asia/Seoul',
    'Australia/Sydney',
    'Australia/Melbourne',
    'Australia/Brisbane',
    'Pacific/Auckland',
    'Africa/Cairo',
    'Africa/Johannesburg',
    'Africa/Lagos',
    'Africa/Nairobi',
];

type DaySchedule = { open: string; close: string } | null;

interface OfficeHoursConfig {
    enabled: boolean;
    timezone: string;
    subject?: string;
    message?: string;
    schedule: Record<string, DaySchedule>;
}

interface Props {
    mailbox: Mailbox & {
        imap_config?: Record<string, string>;
        smtp_config?: Record<string, string>;
        auto_reply_config?: {
            enabled: boolean;
            subject?: string;
            body?: string;
            auto_close_pending_days?: number;
            office_hours?: OfficeHoursConfig;
        };
    };
}

function Label({ htmlFor, children }: { htmlFor?: string; children: React.ReactNode }) {
    return (
        <label htmlFor={htmlFor} className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
            {children}
        </label>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="text-xs text-destructive mt-1.5">{message}</p>;
}

function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            <FieldError message={error} />
        </div>
    );
}

function PasswordInput({
    id,
    value,
    onChange,
    placeholder,
}: {
    id?: string;
    value: string;
    onChange: (v: string) => void;
    placeholder?: string;
}) {
    const [show, setShow] = useState(false);
    return (
        <div className="relative">
            <Input
                id={id}
                type={show ? 'text' : 'password'}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder ?? '••••••••'}
                className="pr-10"
            />
            <button
                type="button"
                onClick={() => setShow(!show)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                aria-label={show ? 'Hide password' : 'Show password'}
            >
                {show ? <EyeOffIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
            </button>
        </div>
    );
}

function NativeSelect({
    id,
    value,
    onChange,
    children,
}: {
    id?: string;
    value: string;
    onChange: (v: string) => void;
    children: React.ReactNode;
}) {
    return (
        <select
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            {children}
        </select>
    );
}

export default function MailboxSettings({ mailbox }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: mailbox.name,
        email: mailbox.email,
        signature: mailbox.signature ?? '',
        active: mailbox.active,

        imap_config: {
            host: mailbox.imap_config?.host ?? '',
            port: mailbox.imap_config?.port ?? '993',
            encryption: mailbox.imap_config?.encryption ?? 'ssl',
            username: mailbox.imap_config?.username ?? '',
            password: mailbox.imap_config?.password ?? '',
        },

        smtp_config: {
            host: mailbox.smtp_config?.host ?? '',
            port: mailbox.smtp_config?.port ?? '587',
            encryption: mailbox.smtp_config?.encryption ?? 'tls',
            username: mailbox.smtp_config?.username ?? '',
            password: mailbox.smtp_config?.password ?? '',
        },

        auto_reply_config: {
            enabled: mailbox.auto_reply_config?.enabled ?? false,
            subject: mailbox.auto_reply_config?.subject ?? '',
            body: mailbox.auto_reply_config?.body ?? '',
            auto_close_pending_days: mailbox.auto_reply_config?.auto_close_pending_days ?? 0,
            office_hours: {
                enabled: mailbox.auto_reply_config?.office_hours?.enabled ?? false,
                timezone: mailbox.auto_reply_config?.office_hours?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
                subject: mailbox.auto_reply_config?.office_hours?.subject ?? '',
                message: mailbox.auto_reply_config?.office_hours?.message ?? '',
                schedule: mailbox.auto_reply_config?.office_hours?.schedule ?? {
                    '1': { open: '09:00', close: '17:00' },
                    '2': { open: '09:00', close: '17:00' },
                    '3': { open: '09:00', close: '17:00' },
                    '4': { open: '09:00', close: '17:00' },
                    '5': { open: '09:00', close: '17:00' },
                },
            },
        },
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/mailboxes/${mailbox.id}`);
    }

    return (
        <AppLayout>
            <Head title={`${mailbox.name} — Settings`} />

            <div className="w-full px-6 py-8">
                {/* Page header */}
                <div className="mb-8">
                    <Link
                        href="/mailboxes"
                        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mb-4"
                    >
                        <ArrowLeftIcon className="h-3.5 w-3.5" />
                        All Mailboxes
                    </Link>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                <MailIcon className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-xl font-semibold tracking-tight">{mailbox.name}</h1>
                                <p className="text-sm text-muted-foreground">{mailbox.email}</p>
                            </div>
                        </div>
                        <Badge variant={mailbox.active ? 'success' : 'secondary'}>{mailbox.active ? 'Active' : 'Inactive'}</Badge>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* General */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <MailIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>General</CardTitle>
                            </div>
                            <CardDescription>Basic mailbox identity and outgoing email signature.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Mailbox name" htmlFor="name" error={errors.name}>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Support"
                                    />
                                </Field>
                                <Field label="Email address" htmlFor="email" error={errors.email}>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="support@example.com"
                                    />
                                </Field>
                            </div>

                            <Field label="Email signature" htmlFor="signature" hint="Appended to the bottom of every outgoing reply.">
                                <textarea
                                    id="signature"
                                    rows={3}
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                    value={data.signature}
                                    onChange={(e) => setData('signature', e.target.value)}
                                    placeholder={'Best regards,\nSupport Team'}
                                />
                            </Field>

                            <Separator />

                            <div className="flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">Mailbox is active</p>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Inactive mailboxes will not fetch emails or send replies.
                                    </p>
                                </div>
                                <Switch id="active" checked={data.active} onCheckedChange={(v) => setData('active', v)} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* IMAP */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ServerIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Incoming Mail (IMAP)</CardTitle>
                            </div>
                            <CardDescription>Configure how FusterAI fetches emails from your inbox.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field label="IMAP host" htmlFor="imap-host">
                                <Input
                                    id="imap-host"
                                    value={data.imap_config.host}
                                    onChange={(e) => setData('imap_config', { ...data.imap_config, host: e.target.value })}
                                    placeholder="imap.gmail.com"
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Port" htmlFor="imap-port">
                                    <Input
                                        id="imap-port"
                                        value={data.imap_config.port}
                                        onChange={(e) => setData('imap_config', { ...data.imap_config, port: e.target.value })}
                                        placeholder="993"
                                    />
                                </Field>
                                <Field label="Encryption" htmlFor="imap-encryption">
                                    <NativeSelect
                                        id="imap-encryption"
                                        value={data.imap_config.encryption}
                                        onChange={(v) => setData('imap_config', { ...data.imap_config, encryption: v })}
                                    >
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                        <option value="none">None</option>
                                    </NativeSelect>
                                </Field>
                            </div>

                            <Separator />

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Username" htmlFor="imap-username">
                                    <Input
                                        id="imap-username"
                                        value={data.imap_config.username}
                                        onChange={(e) => setData('imap_config', { ...data.imap_config, username: e.target.value })}
                                        placeholder="you@gmail.com"
                                    />
                                </Field>
                                <Field
                                    label="Password / App password"
                                    htmlFor="imap-password"
                                    hint="Use an app-specific password for Gmail or Outlook."
                                >
                                    <PasswordInput
                                        id="imap-password"
                                        value={data.imap_config.password}
                                        onChange={(v) => setData('imap_config', { ...data.imap_config, password: v })}
                                    />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    {/* SMTP */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <SendIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Outgoing Mail (SMTP)</CardTitle>
                            </div>
                            <CardDescription>
                                Configure how FusterAI sends replies and notifications on behalf of this mailbox.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field label="SMTP host" htmlFor="smtp-host">
                                <Input
                                    id="smtp-host"
                                    value={data.smtp_config.host}
                                    onChange={(e) => setData('smtp_config', { ...data.smtp_config, host: e.target.value })}
                                    placeholder="smtp.gmail.com"
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Port" htmlFor="smtp-port">
                                    <Input
                                        id="smtp-port"
                                        value={data.smtp_config.port}
                                        onChange={(e) => setData('smtp_config', { ...data.smtp_config, port: e.target.value })}
                                        placeholder="587"
                                    />
                                </Field>
                                <Field label="Encryption" htmlFor="smtp-encryption">
                                    <NativeSelect
                                        id="smtp-encryption"
                                        value={data.smtp_config.encryption}
                                        onChange={(v) => setData('smtp_config', { ...data.smtp_config, encryption: v })}
                                    >
                                        <option value="tls">TLS (recommended)</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </NativeSelect>
                                </Field>
                            </div>

                            <Separator />

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Username" htmlFor="smtp-username">
                                    <Input
                                        id="smtp-username"
                                        value={data.smtp_config.username}
                                        onChange={(e) => setData('smtp_config', { ...data.smtp_config, username: e.target.value })}
                                        placeholder="you@gmail.com"
                                    />
                                </Field>
                                <Field label="Password" htmlFor="smtp-password">
                                    <PasswordInput
                                        id="smtp-password"
                                        value={data.smtp_config.password}
                                        onChange={(v) => setData('smtp_config', { ...data.smtp_config, password: v })}
                                    />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Auto-reply */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <BotIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Auto-Reply</CardTitle>
                            </div>
                            <CardDescription>
                                Automatically acknowledge new conversations so customers know their message was received.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">Enable auto-reply</p>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Send an automatic reply when a new conversation is created.
                                    </p>
                                </div>
                                <Switch
                                    id="auto-reply-enabled"
                                    checked={data.auto_reply_config.enabled}
                                    onCheckedChange={(v) => setData('auto_reply_config', { ...data.auto_reply_config, enabled: v })}
                                />
                            </div>

                            {data.auto_reply_config.enabled && (
                                <>
                                    <Separator />
                                    <Field label="Subject" htmlFor="auto-reply-subject">
                                        <Input
                                            id="auto-reply-subject"
                                            value={data.auto_reply_config.subject}
                                            onChange={(e) =>
                                                setData('auto_reply_config', {
                                                    ...data.auto_reply_config,
                                                    subject: e.target.value,
                                                })
                                            }
                                            placeholder="We received your message"
                                        />
                                    </Field>
                                    <Field label="Body" htmlFor="auto-reply-body">
                                        <textarea
                                            id="auto-reply-body"
                                            rows={5}
                                            className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                            value={data.auto_reply_config.body}
                                            onChange={(e) =>
                                                setData('auto_reply_config', {
                                                    ...data.auto_reply_config,
                                                    body: e.target.value,
                                                })
                                            }
                                            placeholder={
                                                "Hi {{customer_name}},\n\nThanks for reaching out! We've received your message and will get back to you within 1 business day.\n\nBest regards,\nSupport Team"
                                            }
                                        />
                                    </Field>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Office Hours */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ClockIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Office Hours</CardTitle>
                            </div>
                            <CardDescription>Send an out-of-office reply when customers contact you outside working hours.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">Enable office hours</p>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Sends an out-of-hours auto-reply when a message arrives outside your schedule.
                                    </p>
                                </div>
                                <Switch
                                    checked={data.auto_reply_config.office_hours.enabled}
                                    onCheckedChange={(v) =>
                                        setData('auto_reply_config', {
                                            ...data.auto_reply_config,
                                            office_hours: { ...data.auto_reply_config.office_hours, enabled: v },
                                        })
                                    }
                                />
                            </div>

                            {data.auto_reply_config.office_hours.enabled && (
                                <>
                                    <Separator />

                                    <Field label="Timezone" htmlFor="oh-timezone">
                                        <NativeSelect
                                            id="oh-timezone"
                                            value={data.auto_reply_config.office_hours.timezone}
                                            onChange={(v) =>
                                                setData('auto_reply_config', {
                                                    ...data.auto_reply_config,
                                                    office_hours: { ...data.auto_reply_config.office_hours, timezone: v },
                                                })
                                            }
                                        >
                                            {TIMEZONES.map((tz) => (
                                                <option key={tz} value={tz}>
                                                    {tz}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>

                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Weekly schedule</p>
                                        {(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as const).map(
                                            (dayName, idx) => {
                                                const key = String(idx);
                                                const slot = data.auto_reply_config.office_hours.schedule[key] ?? null;
                                                return (
                                                    <div key={key} className="flex items-center gap-3">
                                                        <div className="w-24 flex items-center gap-2">
                                                            <Switch
                                                                checked={slot !== null}
                                                                onCheckedChange={(on) => {
                                                                    const next = { ...data.auto_reply_config.office_hours.schedule };
                                                                    next[key] = on ? { open: '09:00', close: '17:00' } : null;
                                                                    setData('auto_reply_config', {
                                                                        ...data.auto_reply_config,
                                                                        office_hours: {
                                                                            ...data.auto_reply_config.office_hours,
                                                                            schedule: next,
                                                                        },
                                                                    });
                                                                }}
                                                            />
                                                            <span className="text-sm text-muted-foreground w-14">
                                                                {dayName.slice(0, 3)}
                                                            </span>
                                                        </div>
                                                        {slot ? (
                                                            <div className="flex items-center gap-2">
                                                                <Input
                                                                    type="time"
                                                                    className="w-32"
                                                                    value={slot.open}
                                                                    onChange={(e) => {
                                                                        const next = {
                                                                            ...data.auto_reply_config.office_hours.schedule,
                                                                            [key]: { ...slot, open: e.target.value },
                                                                        };
                                                                        setData('auto_reply_config', {
                                                                            ...data.auto_reply_config,
                                                                            office_hours: {
                                                                                ...data.auto_reply_config.office_hours,
                                                                                schedule: next,
                                                                            },
                                                                        });
                                                                    }}
                                                                />
                                                                <span className="text-xs text-muted-foreground">to</span>
                                                                <Input
                                                                    type="time"
                                                                    className="w-32"
                                                                    value={slot.close}
                                                                    onChange={(e) => {
                                                                        const next = {
                                                                            ...data.auto_reply_config.office_hours.schedule,
                                                                            [key]: { ...slot, close: e.target.value },
                                                                        };
                                                                        setData('auto_reply_config', {
                                                                            ...data.auto_reply_config,
                                                                            office_hours: {
                                                                                ...data.auto_reply_config.office_hours,
                                                                                schedule: next,
                                                                            },
                                                                        });
                                                                    }}
                                                                />
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">Closed</span>
                                                        )}
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>

                                    <Separator />

                                    <Field label="Out-of-hours subject" htmlFor="oh-subject" hint="Leave blank to use a default subject.">
                                        <Input
                                            id="oh-subject"
                                            value={data.auto_reply_config.office_hours.subject}
                                            onChange={(e) =>
                                                setData('auto_reply_config', {
                                                    ...data.auto_reply_config,
                                                    office_hours: {
                                                        ...data.auto_reply_config.office_hours,
                                                        subject: e.target.value,
                                                    },
                                                })
                                            }
                                            placeholder="We're currently out of office"
                                        />
                                    </Field>

                                    <Field label="Out-of-hours message" htmlFor="oh-message">
                                        <textarea
                                            id="oh-message"
                                            rows={4}
                                            className="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                                            value={data.auto_reply_config.office_hours.message}
                                            onChange={(e) =>
                                                setData('auto_reply_config', {
                                                    ...data.auto_reply_config,
                                                    office_hours: {
                                                        ...data.auto_reply_config.office_hours,
                                                        message: e.target.value,
                                                    },
                                                })
                                            }
                                            placeholder={
                                                "Hi {{customer_name}},\n\nThanks for reaching out! We're currently outside our office hours and will get back to you as soon as we return.\n\nBest regards,\nSupport Team"
                                            }
                                        />
                                    </Field>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Auto-close */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <BotIcon className="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Auto-Close Stale Conversations</CardTitle>
                            </div>
                            <CardDescription>
                                Automatically close pending conversations when customers haven't replied for a set number of days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field
                                label="Close pending after (days)"
                                htmlFor="auto-close-days"
                                hint="Set to 0 to disable auto-close. Conversations in 'pending' status with no customer reply older than this will be closed daily."
                            >
                                <div className="flex items-center gap-3">
                                    <Input
                                        id="auto-close-days"
                                        type="number"
                                        min={0}
                                        max={365}
                                        className="w-32"
                                        value={data.auto_reply_config.auto_close_pending_days}
                                        onChange={(e) =>
                                            setData('auto_reply_config', {
                                                ...data.auto_reply_config,
                                                auto_close_pending_days: parseInt(e.target.value, 10) || 0,
                                            })
                                        }
                                    />
                                    <span className="text-sm text-muted-foreground">days (0 = disabled)</span>
                                </div>
                            </Field>
                        </CardContent>
                    </Card>

                    {/* WhatsApp channel */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#25D3661f]">
                                        <MessageCircleIcon className="h-5 w-5 text-[#25D366]" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">WhatsApp</CardTitle>
                                        <CardDescription className="text-xs">
                                            Connect a WhatsApp Business number to this mailbox
                                        </CardDescription>
                                    </div>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/mailboxes/${mailbox.id}/whatsapp`}>
                                        Configure
                                        <ArrowRightIcon className="h-3.5 w-3.5 ml-1.5" />
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                    </Card>

                    {/* Security note */}
                    <div className="flex items-start gap-2.5 rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                        <ShieldCheckIcon className="h-4 w-4 mt-0.5 shrink-0 text-success" />
                        <p>IMAP and SMTP credentials are encrypted at rest using AES-256 before being stored in the database.</p>
                    </div>

                    {/* Save */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/mailboxes">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <SaveIcon className="h-4 w-4 mr-2" />
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
