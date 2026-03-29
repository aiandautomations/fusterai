import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import RichTextEditor from '@/Components/RichTextEditor';

interface KnowledgeBase {
    id: number;
    name: string;
}

interface Document {
    id: number;
    title: string;
    content: string;
}

interface Props {
    kb: KnowledgeBase;
    document: Document | null;
}

export default function EditDocument({ kb, document }: Props) {
    const isEditing = !!document;

    const { data, setData, post, patch, processing, errors } = useForm({
        title:   document?.title ?? '',
        content: document?.content ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            patch(route('ai.kb.documents.update', { knowledgeBase: kb.id, document: document!.id }));
        } else {
            post(route('ai.kb.documents.store', { knowledgeBase: kb.id }));
        }
    };

    return (
        <AppLayout>
            <Head title={isEditing ? `Edit: ${document!.title}` : 'New Document'} />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('ai.knowledge-bases.show', kb.id)} className="text-muted-foreground hover:text-foreground text-sm">
                        ← {kb.name}
                    </Link>
                    <span className="text-muted-foreground">/</span>
                    <span className="text-sm">{isEditing ? 'Edit Document' : 'New Document'}</span>
                </div>

                <h1 className="text-3xl font-semibold tracking-tight">{isEditing ? 'Edit Document' : 'New Document'}</h1>

                <Card className="bg-card/75">
                    <CardContent className="p-6">
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="e.g. How do I reset my password?"
                            autoFocus
                        />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Content</Label>
                        <div className="overflow-hidden rounded-lg border border-border/80 bg-background">
                            <RichTextEditor
                                value={data.content}
                                onChange={(html) => setData('content', html)}
                            />
                        </div>
                        {errors.content && <p className="text-sm text-destructive">{errors.content}</p>}
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            {isEditing ? 'Save Changes' : 'Create Document'}
                        </Button>
                        <Link href={route('ai.knowledge-bases.show', kb.id)}>
                            <Button type="button" variant="ghost">Cancel</Button>
                        </Link>
                    </div>
                </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
