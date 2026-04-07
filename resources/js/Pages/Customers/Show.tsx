import React, { useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';
import { getInitials } from '@/lib/utils';
import type { Customer, Conversation } from '@/types';
import { MailIcon, PhoneIcon, BuildingIcon, ExternalLinkIcon, InboxIcon, StickyNoteIcon, CheckIcon } from 'lucide-react';

interface Props {
    customer: Customer & {
        conversations: Conversation[];
        conversations_count: number;
    };
}

const statusColors: Record<string, string> = {
    open:    'default',
    pending: 'warning',
    closed:  'secondary',
    spam:    'destructive',
} as const;

export default function CustomerShow({ customer }: Props) {
    const [notes, setNotes]       = useState(customer.notes ?? '');
    const [saving, setSaving]     = useState(false);
    const [saved, setSaved]       = useState(false);
    const debounceRef             = useRef<ReturnType<typeof setTimeout> | null>(null);

    const saveNotes = async (value: string) => {
        setSaving(true);
        setSaved(false);
        try {
            await fetch(`/customers/${customer.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ notes: value }),
            });
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } finally {
            setSaving(false);
        }
    };

    const handleNotesChange = (value: string) => {
        setNotes(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => saveNotes(value), 800);
    };

    return (
        <AppLayout>
            <Head title={customer.name} />

            <div className="w-full px-6 py-8 space-y-8">
                {/* Header */}
                <Card className="bg-card/75">
                    <CardContent className="p-6">
                        <div className="flex items-start gap-5">
                            <Avatar className="h-16 w-16 text-xl">
                                <AvatarImage src={customer.avatar ?? undefined} alt={customer.name} />
                                <AvatarFallback>{getInitials(customer.name)}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <h1 className="text-3xl font-semibold tracking-tight">{customer.name}</h1>
                                <div className="mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground">
                                    {customer.email && (
                                        <span className="flex items-center gap-1.5">
                                            <MailIcon className="h-3.5 w-3.5" />
                                            {customer.email}
                                        </span>
                                    )}
                                    {customer.phone && (
                                        <span className="flex items-center gap-1.5">
                                            <PhoneIcon className="h-3.5 w-3.5" />
                                            {customer.phone}
                                        </span>
                                    )}
                                    {customer.company && (
                                        <span className="flex items-center gap-1.5">
                                            <BuildingIcon className="h-3.5 w-3.5" />
                                            {customer.company}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {customer.is_blocked && (
                                    <Badge variant="destructive">Blocked</Badge>
                                )}
                                <Badge variant="secondary">{customer.conversations_count} conversations</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Conversation history */}
                    <div className="lg:col-span-2">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-xl font-semibold tracking-tight">Conversation History</h2>
                        </div>

                        {customer.conversations.length === 0 ? (
                            <div className="text-center py-12 border-2 border-dashed border-border rounded-lg">
                                <InboxIcon className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                <p className="text-sm text-muted-foreground">No conversations yet</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {customer.conversations.map((conv) => (
                                    <Link
                                        key={conv.id}
                                        href={`/conversations/${conv.id}`}
                                        className="group flex items-center justify-between rounded-xl border border-border/80 bg-card/70 p-4 transition-colors hover:bg-muted/20"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-base font-semibold group-hover:text-primary transition-colors">
                                                {conv.subject}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {conv.mailbox?.name} · {new Date(conv.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3 ml-4 shrink-0">
                                            <Badge variant={statusColors[conv.status] as any} className="capitalize">
                                                {conv.status}
                                            </Badge>
                                            <ExternalLinkIcon className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Internal notes */}
                    <div>
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <StickyNoteIcon className="h-4 w-4 text-amber-500" />
                                    Internal Notes
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Textarea
                                    value={notes}
                                    onChange={(e) => handleNotesChange(e.target.value)}
                                    placeholder="Add private notes about this customer — visible only to your team…"
                                    className="min-h-[160px] resize-none text-sm"
                                />
                                <div className="flex items-center justify-end gap-2 text-xs text-muted-foreground">
                                    {saved && (
                                        <span className="flex items-center gap-1 text-green-600">
                                            <CheckIcon className="h-3 w-3" />
                                            Saved
                                        </span>
                                    )}
                                    {saving && <span>Saving…</span>}
                                    {!saving && !saved && notes !== (customer.notes ?? '') && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                if (debounceRef.current) clearTimeout(debounceRef.current);
                                                saveNotes(notes);
                                            }}
                                        >
                                            Save
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
