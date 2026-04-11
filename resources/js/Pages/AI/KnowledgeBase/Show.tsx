import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { CheckCircleIcon, Loader2Icon, AlertTriangleIcon, LinkIcon } from 'lucide-react';

interface Document {
    id: number;
    title: string;
    indexed_at: string | null;
    meta: { index_error?: string } | null;
    created_at: string;
    updated_at: string;
}

interface KnowledgeBase {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
}

interface Props {
    kb: KnowledgeBase;
    documents: Document[];
}

export default function Show({ kb, documents }: Props) {
    const deleteDocument = (doc: Document) => {
        if (!confirm(`Delete "${doc.title}"?`)) return;
        router.delete(route('ai.kb.documents.destroy', { knowledgeBase: kb.id, document: doc.id }));
    };

    const deleteKb = () => {
        if (!confirm(`Delete the entire "${kb.name}" knowledge base and all its documents?`)) return;
        router.delete(route('ai.knowledge-bases.destroy', kb.id));
    };

    return (
        <AppLayout>
            <Head title={kb.name} />

            <div className="w-full px-6 py-8 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3 flex-wrap">
                        <Link href={route('ai.kb.index')} className="text-muted-foreground hover:text-foreground text-sm">
                            ← Knowledge Bases
                        </Link>
                        <span className="text-muted-foreground">/</span>
                        <h1 className="text-3xl font-semibold tracking-tight">{kb.name}</h1>
                        {!kb.active && <Badge variant="secondary">Inactive</Badge>}
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('ai.knowledge-bases.edit', kb.id)}>
                            <Button variant="outline" size="sm">Settings</Button>
                        </Link>
                        <Link href={route('ai.kb.documents.create', { knowledgeBase: kb.id }) + '?tab=url'}>
                            <Button variant="outline" size="sm"><LinkIcon className="h-3.5 w-3.5 mr-1.5" />Import URL</Button>
                        </Link>
                        <Link href={route('ai.kb.documents.create', { knowledgeBase: kb.id })}>
                            <Button size="sm">+ New Document</Button>
                        </Link>
                    </div>
                </div>

                {kb.description && <p className="text-muted-foreground text-sm">{kb.description}</p>}

                {/* Documents list */}
                {documents.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="p-12 text-center">
                        <p className="text-muted-foreground">No documents yet.</p>
                        <Link
                            href={route('ai.kb.documents.create', { knowledgeBase: kb.id })}
                            className="mt-4 inline-block"
                        >
                            <Button variant="outline">Add first document</Button>
                        </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="rounded-xl border border-border/80 bg-card/75 divide-y">
                        {documents.map((doc) => (
                            <div key={doc.id} className="flex items-center justify-between px-4 py-3 hover:bg-muted/25">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Link
                                            href={route('ai.kb.documents.edit', { knowledgeBase: kb.id, document: doc.id })}
                                            className="font-medium hover:underline"
                                        >
                                            {doc.title}
                                        </Link>
                                        {doc.meta?.index_error ? (
                                            <span title={doc.meta.index_error} className="flex items-center gap-1 text-[11px] text-destructive bg-destructive/10 px-1.5 py-0.5 rounded">
                                                <AlertTriangleIcon className="h-3 w-3" /> Index failed
                                            </span>
                                        ) : doc.indexed_at ? (
                                            <span className="flex items-center gap-1 text-[11px] text-success bg-success/10 px-1.5 py-0.5 rounded">
                                                <CheckCircleIcon className="h-3 w-3" /> Indexed
                                            </span>
                                        ) : (
                                            <span className="flex items-center gap-1 text-[11px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                <Loader2Icon className="h-3 w-3 animate-spin" /> Indexing…
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Updated {new Date(doc.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Link href={route('ai.kb.documents.edit', { knowledgeBase: kb.id, document: doc.id })}>
                                        <Button variant="ghost" size="sm">Edit</Button>
                                    </Link>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => deleteDocument(doc)}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Danger zone */}
                <div className="border border-destructive/30 rounded-xl p-4">
                    <h3 className="text-sm font-medium text-destructive mb-2">Danger Zone</h3>
                    <p className="text-sm text-muted-foreground mb-3">
                        Deleting this knowledge base will permanently remove all {documents.length} document{documents.length !== 1 ? 's' : ''}.
                    </p>
                    <Button variant="destructive" size="sm" onClick={deleteKb}>
                        Delete Knowledge Base
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
