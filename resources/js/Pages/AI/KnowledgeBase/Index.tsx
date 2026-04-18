import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';

interface KnowledgeBase {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    documents_count: number;
    created_at: string;
}

interface Props {
    knowledgeBases: KnowledgeBase[];
}

export default function Index({ knowledgeBases }: Props) {
    return (
        <AppLayout>
            <Head title="Knowledge Base" />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">Knowledge Base</h1>
                        <p className="text-sm text-muted-foreground mt-1">Documents used by AI to suggest replies and answer questions.</p>
                    </div>
                    <Link href={route('ai.knowledge-bases.create')}>
                        <Button>New Knowledge Base</Button>
                    </Link>
                </div>

                {knowledgeBases.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="p-12 text-center">
                            <p className="text-muted-foreground">No knowledge bases yet.</p>
                            <Link href={route('ai.knowledge-bases.create')} className="mt-4 inline-block">
                                <Button variant="outline">Create your first</Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {knowledgeBases.map((kb) => (
                            <Link
                                key={kb.id}
                                href={route('ai.knowledge-bases.show', kb.id)}
                                className="block rounded-xl border border-border/80 bg-card/70 p-4 transition-colors hover:bg-muted/25"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <h2 className="text-lg font-semibold">{kb.name}</h2>
                                        {!kb.active && <Badge variant="secondary">Inactive</Badge>}
                                    </div>
                                    <span className="text-sm text-muted-foreground">
                                        {kb.documents_count} document{kb.documents_count !== 1 ? 's' : ''}
                                    </span>
                                </div>
                                {kb.description && <p className="text-sm text-muted-foreground mt-1">{kb.description}</p>}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
