import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { getInitials } from '@/lib/utils';
import type { Customer, Conversation } from '@/types';
import { MailIcon, PhoneIcon, BuildingIcon, ExternalLinkIcon, InboxIcon } from 'lucide-react';

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
                            <Badge variant="secondary">{customer.conversations_count} conversations</Badge>
                        </div>
                    </CardContent>
                </Card>

                {/* Conversation history */}
                <div>
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
            </div>
        </AppLayout>
    );
}
