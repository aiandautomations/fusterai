import React from 'react';
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';

interface Props {
    workspace: { name: string; slug: string };
    kb: { name: string };
    document: {
        id: number;
        title: string;
        content: string;
        source_url: string | null;
        indexed_at: string | null;
    };
}

export default function KnowledgeBaseShow({ workspace, kb, document }: Props) {
    return (
        <PortalLayout workspace={workspace} title={document.title}>
            <Head title={`${document.title} — ${workspace.name}`} />

            <div className="mb-4">
                <Link href={route('portal.kb.index', workspace.slug)} className="text-sm text-violet-600 hover:underline">
                    ← Back to articles
                </Link>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <div className="flex items-center gap-2 mb-1 text-xs text-gray-400">
                    <span>{kb.name}</span>
                    {document.indexed_at && (
                        <>
                            <span>·</span>
                            <span>Updated {new Date(document.indexed_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </>
                    )}
                </div>

                <h1 className="text-lg font-semibold text-gray-900 mb-5">{document.title}</h1>

                <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                    {document.content}
                </div>

                {document.source_url && (
                    <div className="mt-6 pt-4 border-t border-gray-100">
                        <a
                            href={document.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-violet-600 hover:underline"
                        >
                            View original source →
                        </a>
                    </div>
                )}
            </div>

            <div className="mt-6 text-center">
                <p className="text-sm text-gray-500">
                    Still need help?{' '}
                    <Link href={route('portal.tickets.create', workspace.slug)} className="text-violet-600 hover:underline">
                        Submit a support ticket
                    </Link>
                </p>
            </div>
        </PortalLayout>
    );
}
