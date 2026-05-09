import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button } from '@/Components/ui/button';

interface Thread {
    id: number;
    body: string;
    from_customer: boolean;
    author: string;
    created_at: string;
}

interface Ticket {
    id: number;
    subject: string;
    status: string;
    priority: string;
    created_at: string;
    threads: Thread[];
}

interface Props {
    workspace: { name: string; slug: string };
    ticket: Ticket;
}

const statusColors: Record<string, string> = {
    open: 'bg-green-50 text-green-700 border-green-200',
    pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    closed: 'bg-gray-100 text-gray-600 border-gray-200',
};

function formatDate(iso: string) {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function TicketsShow({ workspace, ticket }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('portal.tickets.reply', [workspace.slug, ticket.id]), {
            onSuccess: () => reset('body'),
        });
    }

    const isClosed = ticket.status === 'closed';

    return (
        <PortalLayout workspace={workspace} title={ticket.subject}>
            <Head title={`${ticket.subject} — ${workspace.name}`} />

            <div className="mb-4 flex items-center gap-3">
                <Link href={route('portal.tickets.index', workspace.slug)} className="text-sm text-violet-600 hover:underline">
                    ← Back to tickets
                </Link>
                <span className={`text-xs font-medium px-2 py-0.5 rounded-full border capitalize ${statusColors[ticket.status] ?? ''}`}>
                    {ticket.status}
                </span>
            </div>

            <div className="space-y-3 mb-6">
                {ticket.threads.map((thread) => (
                    <div
                        key={thread.id}
                        className={`rounded-xl border p-4 ${thread.from_customer ? 'bg-white border-gray-200' : 'bg-violet-50 border-violet-200'}`}
                    >
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-medium text-gray-700">{thread.author}</span>
                            {!thread.from_customer && <span className="text-xs text-violet-600 font-medium">Support team</span>}
                            <span className="text-xs text-gray-400 ml-auto">{formatDate(thread.created_at)}</span>
                        </div>
                        <div
                            className="text-sm text-gray-700 prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{ __html: thread.body }}
                        />
                    </div>
                ))}
            </div>

            {!isClosed && (
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 className="text-sm font-medium text-gray-900 mb-3">Add a reply</h3>
                    <form onSubmit={submit} className="space-y-3">
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Write your reply…"
                            rows={5}
                            className={`w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-y ${errors.body ? 'border-red-400' : 'border-gray-300'}`}
                        />
                        {errors.body && <p className="text-xs text-red-600">{errors.body}</p>}
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Sending…' : 'Send reply'}
                        </Button>
                    </form>
                </div>
            )}

            {isClosed && (
                <div className="text-center py-6 text-sm text-gray-400">
                    This ticket is closed.{' '}
                    <Link href={route('portal.tickets.create', workspace.slug)} className="text-violet-600 hover:underline">
                        Submit a new ticket
                    </Link>
                </div>
            )}
        </PortalLayout>
    );
}
