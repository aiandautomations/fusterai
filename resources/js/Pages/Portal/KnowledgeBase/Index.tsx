import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';

interface Document {
    id: number;
    title: string;
    excerpt: string;
    indexed_at: string | null;
}

interface Props {
    workspace: { name: string; slug: string };
    kb: { name: string; description: string | null } | null;
    documents: Document[];
}

export default function KnowledgeBaseIndex({ workspace, kb, documents }: Props) {
    const [search, setSearch] = useState('');

    const filtered = search
        ? documents.filter(
              (d) =>
                  d.title.toLowerCase().includes(search.toLowerCase()) ||
                  d.excerpt.toLowerCase().includes(search.toLowerCase()),
          )
        : documents;

    return (
        <PortalLayout workspace={workspace} title="Help articles">
            <Head title={`Help articles — ${workspace.name}`} />

            {!kb ? (
                <div className="bg-white rounded-xl border border-gray-200 p-10 text-center">
                    <p className="text-sm text-gray-500">No knowledge base available yet.</p>
                </div>
            ) : (
                <>
                    {kb.description && (
                        <p className="text-sm text-gray-500 mb-5">{kb.description}</p>
                    )}

                    <div className="mb-5">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search articles…"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                        />
                    </div>

                    {filtered.length === 0 ? (
                        <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                            <p className="text-sm text-gray-500">No articles found.</p>
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {filtered.map((doc) => (
                                <Link
                                    key={doc.id}
                                    href={route('portal.kb.show', [workspace.slug, doc.id])}
                                    className="block px-5 py-4 hover:bg-gray-50 transition-colors"
                                >
                                    <p className="text-sm font-medium text-gray-900 mb-1">{doc.title}</p>
                                    <p className="text-xs text-gray-500 line-clamp-2">{doc.excerpt}</p>
                                </Link>
                            ))}
                        </div>
                    )}
                </>
            )}

            <div className="mt-6 text-center">
                <p className="text-sm text-gray-500">
                    Can't find what you're looking for?{' '}
                    <Link href={route('portal.tickets.create', workspace.slug)} className="text-violet-600 hover:underline">
                        Submit a support ticket
                    </Link>
                </p>
            </div>
        </PortalLayout>
    );
}
