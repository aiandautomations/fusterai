import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props {
    workspace: { name: string; slug: string };
}

export default function TicketsCreate({ workspace }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        subject: '',
        body: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('portal.tickets.store', workspace.slug));
    }

    return (
        <PortalLayout workspace={workspace} title="Submit a ticket">
            <Head title={`New ticket — ${workspace.name}`} />

            <div className="mb-4">
                <Link href={route('portal.tickets.index', workspace.slug)} className="text-sm text-violet-600 hover:underline">
                    ← Back to tickets
                </Link>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label className="text-sm font-medium text-gray-700">Subject</Label>
                        <Input
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            placeholder="Brief description of your issue"
                            autoFocus
                            className={errors.subject ? 'border-red-400' : ''}
                        />
                        {errors.subject && <p className="text-xs text-red-600">{errors.subject}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-sm font-medium text-gray-700">Message</Label>
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Describe your issue in detail…"
                            rows={8}
                            className={`w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-y ${errors.body ? 'border-red-400' : 'border-gray-300'}`}
                        />
                        {errors.body && <p className="text-xs text-red-600">{errors.body}</p>}
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Submitting…' : 'Submit ticket'}
                        </Button>
                        <Link
                            href={route('portal.tickets.index', workspace.slug)}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </PortalLayout>
    );
}
