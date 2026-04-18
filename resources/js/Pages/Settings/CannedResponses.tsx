import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { PencilIcon, TrashIcon, PlusIcon, XIcon, GlobeIcon, MailboxIcon } from 'lucide-react';

interface Mailbox {
    id: number;
    name: string;
}
interface CannedResponse {
    id: number;
    name: string;
    content: string;
    mailbox_id: number | null;
    mailbox?: { id: number; name: string } | null;
}
interface Props {
    responses: CannedResponse[];
    mailboxes: Mailbox[];
}

function MailboxSelect({
    value,
    onChange,
    mailboxes,
    includeAll,
}: {
    value: number | null | '';
    onChange: (v: number | null) => void;
    mailboxes: Mailbox[];
    includeAll?: boolean;
}) {
    return (
        <select
            value={value === null ? '' : String(value)}
            onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
            className="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary bg-background"
        >
            {includeAll && <option value="">All mailboxes</option>}
            <option value="">Workspace-wide (all mailboxes)</option>
            {mailboxes.map((m) => (
                <option key={m.id} value={m.id}>
                    {m.name}
                </option>
            ))}
        </select>
    );
}

function CannedResponseRow({ response, mailboxes }: { response: CannedResponse; mailboxes: Mailbox[] }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing } = useForm({
        name: response.name,
        content: response.content,
        mailbox_id: response.mailbox_id as number | null,
    });

    function save(e: React.FormEvent) {
        e.preventDefault();
        patch(`/settings/canned-responses/${response.id}`, { onSuccess: () => setEditing(false) });
    }

    if (editing) {
        return (
            <form onSubmit={save} className="py-4 space-y-3 border-t first:border-t-0">
                <div className="grid grid-cols-3 gap-3">
                    <div className="space-y-1">
                        <label className="text-xs font-medium text-muted-foreground">Shortcut name</label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. thanks" required />
                    </div>
                    <div className="col-span-2 space-y-1">
                        <label className="text-xs font-medium text-muted-foreground">Response content</label>
                        <textarea
                            className="w-full border rounded-md px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                            rows={3}
                            value={data.content}
                            onChange={(e) => setData('content', e.target.value)}
                            required
                        />
                    </div>
                </div>
                <div className="space-y-1">
                    <label className="text-xs font-medium text-muted-foreground">Available in</label>
                    <MailboxSelect value={data.mailbox_id} onChange={(v) => setData('mailbox_id', v)} mailboxes={mailboxes} />
                </div>
                <div className="flex gap-2">
                    <Button type="submit" size="sm" disabled={processing}>
                        Save
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(false)}>
                        Cancel
                    </Button>
                </div>
            </form>
        );
    }

    return (
        <div className="py-4 border-t first:border-t-0 flex items-start gap-4 group">
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1 flex-wrap">
                    <span className="inline-flex items-center rounded-md bg-primary/10 text-primary text-xs font-mono font-medium px-2 py-0.5">
                        /{response.name}
                    </span>
                    {response.mailbox_id ? (
                        <Badge variant="outline" className="text-[11px] gap-1">
                            <MailboxIcon className="h-3 w-3" />
                            {response.mailbox?.name ?? 'Mailbox-specific'}
                        </Badge>
                    ) : (
                        <Badge variant="secondary" className="text-[11px] gap-1">
                            <GlobeIcon className="h-3 w-3" />
                            All mailboxes
                        </Badge>
                    )}
                </div>
                <p className="text-sm text-muted-foreground line-clamp-2">{response.content}</p>
            </div>
            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => setEditing(true)}>
                    <PencilIcon className="h-3.5 w-3.5" />
                </Button>
                <Button
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 text-destructive hover:text-destructive"
                    onClick={() => confirm(`Delete "/${response.name}"?`) && router.delete(`/settings/canned-responses/${response.id}`)}
                >
                    <TrashIcon className="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    );
}

function AddForm({ mailboxes }: { mailboxes: Mailbox[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm<{ name: string; content: string; mailbox_id: number | null }>({
        name: '',
        content: '',
        mailbox_id: null,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/canned-responses', {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    }

    if (!open) {
        return (
            <Button size="sm" onClick={() => setOpen(true)}>
                <PlusIcon className="h-4 w-4 mr-1" /> Add Canned Response
            </Button>
        );
    }

    return (
        <form onSubmit={submit} className="border rounded-lg p-4 space-y-3 bg-muted/30">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium">New Canned Response</p>
                <button type="button" onClick={() => setOpen(false)} className="text-muted-foreground hover:text-foreground">
                    <XIcon className="h-4 w-4" />
                </button>
            </div>
            <div className="grid grid-cols-3 gap-3">
                <div className="space-y-1">
                    <label className="text-xs font-medium text-muted-foreground">Shortcut name</label>
                    <div className="flex items-center border rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-primary">
                        <span className="px-2 text-sm text-muted-foreground bg-muted border-r">/</span>
                        <input
                            className="flex-1 px-2 py-2 text-sm focus:outline-none bg-background"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value.replace(/\s/g, '-').toLowerCase())}
                            placeholder="thanks"
                            required
                        />
                    </div>
                </div>
                <div className="col-span-2 space-y-1">
                    <label className="text-xs font-medium text-muted-foreground">Response content</label>
                    <textarea
                        className="w-full border rounded-md px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                        rows={3}
                        value={data.content}
                        onChange={(e) => setData('content', e.target.value)}
                        placeholder="Thank you for reaching out! We'll get back to you shortly."
                        required
                    />
                </div>
            </div>
            <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Available in</label>
                <MailboxSelect value={data.mailbox_id} onChange={(v) => setData('mailbox_id', v)} mailboxes={mailboxes} />
                <p className="text-xs text-muted-foreground">Leave as "Workspace-wide" to show in all mailboxes.</p>
            </div>
            <Button type="submit" size="sm" disabled={processing}>
                {processing ? 'Creating...' : 'Create'}
            </Button>
        </form>
    );
}

export default function SettingsCannedResponses({ responses, mailboxes }: Props) {
    const [filterMailbox, setFilterMailbox] = useState<number | null | ''>('');

    const filtered =
        filterMailbox === ''
            ? responses
            : filterMailbox === null
              ? responses.filter((r) => r.mailbox_id === null)
              : responses.filter((r) => r.mailbox_id === filterMailbox);

    const workspaceWide = filtered.filter((r) => r.mailbox_id === null);
    const mailboxSpecific = filtered.filter((r) => r.mailbox_id !== null);

    return (
        <AppLayout title="Canned Responses">
            <div className="w-full px-6 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Canned Responses</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Save common replies and insert them by typing{' '}
                        <kbd className="px-1.5 py-0.5 rounded border text-xs font-mono">/</kbd> in the reply box. Workspace-wide responses
                        appear in all mailboxes; mailbox-specific ones appear only in that mailbox.
                    </p>
                </div>

                <div className="flex items-center justify-between gap-4 flex-wrap">
                    <AddForm mailboxes={mailboxes} />
                    {mailboxes.length > 0 && (
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">Filter:</span>
                            <select
                                value={filterMailbox === null ? 'null' : String(filterMailbox)}
                                onChange={(e) =>
                                    setFilterMailbox(e.target.value === '' ? '' : e.target.value === 'null' ? null : Number(e.target.value))
                                }
                                className="border rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary bg-background"
                            >
                                <option value="">All responses ({responses.length})</option>
                                <option value="null">Workspace-wide ({responses.filter((r) => !r.mailbox_id).length})</option>
                                {mailboxes.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({responses.filter((r) => r.mailbox_id === m.id).length})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* Workspace-wide */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <GlobeIcon className="h-4 w-4 text-muted-foreground" />
                            Workspace-wide
                            <Badge variant="secondary" className="ml-auto">
                                {workspaceWide.length}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {workspaceWide.length === 0 ? (
                            <p className="text-sm text-muted-foreground py-4 text-center">No workspace-wide responses yet.</p>
                        ) : (
                            workspaceWide.map((r) => <CannedResponseRow key={r.id} response={r} mailboxes={mailboxes} />)
                        )}
                    </CardContent>
                </Card>

                {/* Mailbox-specific */}
                {mailboxSpecific.length > 0 && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm flex items-center gap-2">
                                <MailboxIcon className="h-4 w-4 text-muted-foreground" />
                                Mailbox-specific
                                <Badge variant="secondary" className="ml-auto">
                                    {mailboxSpecific.length}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {mailboxSpecific.map((r) => (
                                <CannedResponseRow key={r.id} response={r} mailboxes={mailboxes} />
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
