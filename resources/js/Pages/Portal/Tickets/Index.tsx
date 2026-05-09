import React from 'react';
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';

interface Ticket {
    id: number;
    subject: string;
    status: string;
    priority: string;
    last_reply_at: string | null;
    created_at: string;
}

interface Props {
    workspace: { name: string; slug: string };
    customer: { name: string; email: string };
    tickets: Ticket[];
}

const statusColors: Record<string, string> = {
    open: 'bg-green-50 text-green-700 border-green-200',
    pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    closed: 'bg-gray-100 text-gray-600 border-gray-200',
    spam: 'bg-red-50 text-red-600 border-red-200',
};

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

export default function TicketsIndex({ workspace, customer, tickets }: Props) {
    return (
        <PortalLayout workspace={workspace} customer={customer} title="My tickets">
            <Head title={`My tickets — ${workspace.name}`} />

            <div className="flex items-center justify-between mb-5">
                <p className="text-sm text-gray-500">{tickets.length} ticket{tickets.length !== 1 ? 's' : ''}</p>
                <Link
                    href={route('portal.tickets.create', workspace.slug)}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 px-3.5 py-1.5 rounded-lg transition-colors"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                    New ticket
                </Link>
            </div>

            {tickets.length === 0 ? (
                <div className="bg-white rounded-xl border border-gray-200 p-10 text-center">
                    <p className="text-sm text-gray-500 mb-3">You haven't submitted any tickets yet.</p>
                    <Link
                        href={route('portal.tickets.create', workspace.slug)}
                        className="text-sm font-medium text-violet-600 hover:underline"
                    >
                        Submit your first ticket →
                    </Link>
                </div>
            ) : (
                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {tickets.map((ticket) => (
                        <Link
                            key={ticket.id}
                            href={route('portal.tickets.show', [workspace.slug, ticket.id])}
                            className="flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition-colors"
                        >
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate">{ticket.subject}</p>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    Opened {formatDate(ticket.created_at)}
                                    {ticket.last_reply_at && ` · Updated ${formatDate(ticket.last_reply_at)}`}
                                </p>
                            </div>
                            <span className={`ml-4 shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border capitalize ${statusColors[ticket.status] ?? ''}`}>
                                {ticket.status}
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </PortalLayout>
    );
}
